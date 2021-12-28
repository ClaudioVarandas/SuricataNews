<?php

namespace App\Console\Commands\Covid19PT;

use App\Models\RptDaily;
use App\Notifications\Covid19ReportDaily;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Statement;

class Covid19PortugalDailyUpdate extends Command
{
    protected $signature = 'covid19pt:daily-update';

    public function handle()
    {
        try {
            $updatedItemsCounter = 0;

            $csv = file_get_contents('https://raw.githubusercontent.com/dssg-pt/covid19pt-data/master/data.csv');

            if (Storage::disk('local_data')->exists('/covid19/data.csv')) {
                Storage::disk('local_data')->delete('/covid19/data.csv');
            }

            Storage::disk('local_data')->put('/covid19/data.csv', $csv);

            $stream = Storage::disk('local_data')->readStream('/covid19/data.csv');
            $reader = Reader::createFromStream($stream);
            $reader->setHeaderOffset(0);
            $records = Statement::create()->process($reader);


            $lastRecord = RptDaily::query()->orderBy('id', 'desc')->first();
            $lastUpdateDateObj = $lastRecord->date;

            foreach ($records as $record) {

                $recordDateObj = Carbon::createFromDate($record['data']);

                if ($lastUpdateDateObj < $recordDateObj) {
                    $daily = new RptDaily();
                    $daily->date = $record['data'];
                    $daily->record_date = $record['data_dados'];
                    $daily->json_raw = $record;
                    $result = $daily->save();

                    if ($result) {
                        $updatedItemsCounter++;
                        $previousDateObj = $lastUpdateDateObj->subDay();
                        $previousReportDaily = RptDaily::where('date', $previousDateObj->format('Y-m-d'))->first();

                        $deathsVariation = '';
                        $deathsVariationBalance = 0;
                        $newConfirmedVariation = '';
                        $newConfirmedBalance = 0;
                        $currentReportData = $daily->json_raw;

                        if (!empty($previousReportDaily)) {
                            $daily->fresh();

                            $previousReportDailyData = $previousReportDaily->json_raw;

                            $deathsVariationBalance = $currentReportData['obitos'] - $previousReportDailyData['obitos'];
                            $deathsVariation = $deathsVariationBalance > 0 ? '+' : '-';

                            $newConfirmedBalance = $currentReportData['confirmados_novos'] - $previousReportDailyData['confirmados_novos'];
                            $newConfirmedVariation = $newConfirmedBalance > 0 ? '+' : '-';
                        }

                        $content = $currentReportData;
                        $content['obitos_var'] = sprintf("%s%d", $deathsVariation, $deathsVariationBalance);
                        $content['confirmados_novos_var'] = sprintf("%s%d", $newConfirmedVariation, $newConfirmedBalance);

                        Notification::route('telegram', config('services.telegram-bot-api.chat_id'))->notify(new Covid19ReportDaily($content));

                        $this->info(sprintf('New report *' . $record['data'] . '*'));
                    }
                }
            }
        }catch (Exception $e) {
            $this->error($e->getMessage() . PHP_EOL);
        }

        $this->info(sprintf('Updated ' . $updatedItemsCounter.' records.'));

        return 0;
    }
}
