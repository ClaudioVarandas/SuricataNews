<?php

namespace App\Console\Commands\Covid19PT;

use App\Models\RptCounty;
use App\Models\RptDaily;
use App\Notifications\Covid19ReportCounty;
use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class Covid19PortugalCountyUpdate extends Command
{
    protected $signature = 'covid19pt:county-update';

    protected $client;

    public function __construct()
    {
        parent::__construct();

        $this->client = new HttpClient();
    }

    public function handle()
    {
        $vostBaseURl = config('services.vost_covid19_rest_api.base_url');
        $uri = sprintf('%s/%s', $vostBaseURl, 'get_last_update_counties');
        $response = $this->client->get($uri);
        $data = json_decode($response->getBody(), true);


        $updatedCountiesCollection = new Collection();

        $dateUpdate = now()->format('Y-m-d');

        foreach ($data as $item) {
            $dateUpdate = Carbon::parse($item['data'])->format('Y-m-d');
            $countyRpt = RptCounty::where('date', $dateUpdate)
                ->where('name', $item['concelho'])
                ->where('district', $item['distrito'])
                ->first();

            if ($countyRpt) {
                continue;
            }

            $countyRptModel = RptCounty::create([
                'date' => $item['data'],
                'name' => $item['concelho'],
                'district' => $item['distrito'],
                'json_raw' => $item
            ]);

            if ($countyRptModel) {
                $updatedCountiesCollection->push($countyRptModel);
            }

        }

        $this->info(sprintf("%s new counties reported.", $updatedCountiesCollection->count()));

        $updatedCountiesCollection->sortBy(function ($model) {
            return $model->name;
        });

        foreach (array_chunk($updatedCountiesCollection->toArray(), 40, true) as $dataChunk) {
            Notification::route('telegram', config('services.telegram-bot-api.chat_id'))
                ->notify(new Covid19ReportCounty($dateUpdate, $dataChunk));
        }

        return 0;
    }


}
