<?php

namespace App\Console\Commands\Covid19PT;

use App\Models\RptDaily;
use Illuminate\Console\Command;

class Covid19PortugalFullUpdateDaily extends Command
{
    protected $signature = 'covid19pt:full-update-daily';

    public function handle()
    {
        $dataSourceUrl = config('services.dssg_pt_covid19.full_daily');
        $csvData = file_get_contents($dataSourceUrl);
        $rows = explode("\n", $csvData);

        RptDaily::truncate();

        $bar = $this->output->createProgressBar(count($rows) - 1);
        $bar->start();
        $storedItemsCounter = 0;
        $header = null;
        foreach ($rows as $row) {

            if (!$header) {
                $header = str_getcsv($row);
            } else {

                $rowArray = str_getcsv($row);
                if (empty($row) || count($rowArray) !== count($header)) {
                    continue;
                }

                $item = array_combine($header, $rowArray);

                try {
                    $daily = new RptDaily();
                    $daily->date = $item['data'];
                    $daily->record_date = $item['data_dados'];
                    $daily->json_raw = $item;
                    $result = $daily->save();

                    if ($result) {
                        $storedItemsCounter++;
                    }
                    $bar->advance();

                } catch (\Throwable $t) {
                    $this->error($t->getMessage() . PHP_EOL);
                }
            }
        }
        $bar->finish();
        $this->newLine();
        $this->info(sprintf("Finish. Stored %s records.", $storedItemsCounter));

        return Command::SUCCESS;
    }

}
