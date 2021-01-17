<?php

namespace App\Console\Commands\Covid19PT;

use App\Models\RptDaily;
use App\Notifications\Covid19ReportDaily;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class Covid19PortugalDailyUpdate extends Command
{
    protected $signature = 'covid19pt:daily-update';

    protected $client;

    public function __construct()
    {
        parent::__construct();

        $this->client = new Client();
    }

    public function handle()
    {
        $vostBaseURl = config('services.vost_covid19_rest_api.base_url');
        $uri = sprintf('%s/%s', $vostBaseURl, 'get_last_update');
        $response = $this->client->get($uri);
        $content = json_decode($response->getBody(), true);

        $lastUpdateDateObj = Carbon::parse($content['data']);
        $reportDaily = RptDaily::where('date', $lastUpdateDateObj->format('Y-m-d'))->first();

        if ($reportDaily) {
            return 0;
        }

        $daily = new RptDaily();
        $daily->date = $content['data'];
        $daily->record_date = $content['data_dados'];
        $daily->json_raw = $content;
        $result = $daily->save();

        $previousDateObj = $lastUpdateDateObj->subDay();
        $previousReportDaily = RptDaily::where('date', $previousDateObj->format('Y-m-d'))->first();
        $previousReportDailyData = $previousReportDaily->json_raw;

        $deathsVariationBalance = $content['obitos'] - $previousReportDailyData['obitos'];
        $deathsVariation = $deathsVariationBalance > 0 ? '+' : '-';
        $content['obitos_balanco_diario'] = sprintf("%s%d", $deathsVariation, $deathsVariationBalance);

        if($result){
            Notification::route('telegram', config('services.telegram-bot-api.chat_id'))
                ->notify(new Covid19ReportDaily($content));
        }

        return 0;
    }


}
