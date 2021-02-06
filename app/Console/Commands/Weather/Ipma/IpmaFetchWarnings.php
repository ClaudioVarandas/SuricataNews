<?php

namespace App\Console\Commands\Weather\Ipma;

use App\Notifications\Covid19ReportDaily;
use App\Notifications\IpmaWarnings;
use GuzzleHttp\Client;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class IpmaFetchWarnings extends Command
{
    /**
     * Awareness levels.
     *
     * Yellow : Weather sensitive activities may be affected
     * Orange : Weather conditions involving moderate to high risk
     * Red    : Weather conditions involving high risk
     *
     */

    private const AWARENESS_LEVEL_YELLOW = 'yellow';
    private const AWARENESS_LEVEL_ORANGE = 'orange';
    private const AWARENESS_LEVEL_RED = 'red';

    private const ALLOWED_AWARENESS_LEVELS = [
        self::AWARENESS_LEVEL_YELLOW => 'Amarelo',
        self::AWARENESS_LEVEL_ORANGE => 'Laranja',
        self::AWARENESS_LEVEL_RED => 'red',
    ];

    private const AWARENESS_LEVELS_LABELS = [

    ];
    /**
     * Best effort IPMA Area to County code mapper.
     *
     * @see http://api.ipma.pt/open-data/distrits-islands.json
     */
    private const AREA_MAPPER = [
        'AVR' => 'Aveiro',
        'BJA' => 'Beja',
        'BRG' => 'Braga',
        'BGC' => 'Bragança',
        'CBO' => 'Castelo Branco',
        'CBR' => 'Coimbra',
        'EVR' => 'Evora',
        'FAR' => 'Faro',
        'GDA' => 'Guarda',
        'LRA' => 'Leiria',
        'LSB' => 'Lisboa', // Mainland [Lisboa, Lisboa - Jardim Botânico]
        'PTG' => 'Portalegre',
        'PTO' => 'Porto',
        'STM' => 'Santarem',
        'STB' => 'Setubal',
        'VCT' => 'Viana do Castelo',
        'VRL' => 'Vila Real',
        'VIS' => 'Viseu',
        'MCN' => 'Madeira', // Madeira - Costa Norte [São Vicente]
        'MCS' => 'Madeira', // Madeira - Costa Sul [Funchal]
        'MRM' => 'Madeira', // Madeira - R. Montanhosas [Santana]
        'MPS' => 'Madeira', // Madeira - Porto Santo
        'AOR' => 'Açores - Grupo Oriental', // Açores - Grupo Oriental [Ponta Delgada, Vila do Porto]
        'ACE' => 'Açores - Grupo Central', // Açores - Grupo Central [Angra do Heroísmo, Santa Cruz da Graciosa, Velas, Madalena, Horta]
        'AOC' => 'Açores - Grupo Ocidental', // Açores - Grupo Ocidental [Santa Cruz das Flores, Vila do Corvo]
    ];

    protected $signature = 'weather:ipma:fetch-warnings';

    protected Client $httpClient;

    protected array $countiesData;

    public function __construct()
    {
        parent::__construct();

        $this->httpClient = new HttpClient();
    }

    public function handle()
    {
        $url = sprintf('%s%s',
            config('services.ipma.api_base_url'),
            '/json/warnings_www.json'
        );

        $result = $this->httpClient->get($url);

        $data = $result->getBody()->getContents();
        $data = json_decode($data, true);

        $warningsData = collect($data)
            ->filter(static function (array $warning) {
                return in_array($warning['awarenessLevelID'], array_keys(self::ALLOWED_AWARENESS_LEVELS), true);
            })->map(function (array $warning) {
                return [
                    'text' => $warning['text'],
                    'type_name' => $warning['awarenessTypeName'],
                    'type_level' => $warning['awarenessLevelID'],
                    'id_area' => $warning['idAreaAviso'],
                    'county' => self::AREA_MAPPER[$warning['idAreaAviso']],
                    'start_time' => Carbon::parse($warning['startTime']),
                    'end_time' => Carbon::parse($warning['endTime'])
                ];
            });

        $warningsData = $warningsData->sortBy('county')->sortBy('type_level');

        Cache::forever('ipma_warnings', $warningsData);

        $notificationData = [];

        foreach ($warningsData->toArray() as $warning) {
            $notificationData[] = [
                'county' => $warning['county'],
                'type_level' => self::ALLOWED_AWARENESS_LEVELS[$warning['type_level']],
                'type_name' => $warning['type_name'],
                'text' => $warning['text'],
                'end_time' => $warning['end_time'],
            ];
        }

        if (!empty($notificationData)) {
            Notification::route('telegram', config('services.telegram-bot-api.chat_id'))
                ->notify(new IpmaWarnings($notificationData));
        }
    }

    protected function loadIpmaCountiesData()
    {
        $filePath = 'ipma/districts_islands_pt.json';

        if (!Storage::disk('local_data')->exists($filePath)) {
            throw new \Exception(sprintf("Required data file %s not found.", $filePath));
        }

        $countiesData = Storage::disk('local_data')->get($filePath);
        $countiesData = json_decode($countiesData, true);
        $this->countiesData = $countiesData['data'];
    }
}
