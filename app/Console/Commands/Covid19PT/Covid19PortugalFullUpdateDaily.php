<?php

namespace App\Console\Commands\Covid19PT;

use App\Models\RptDaily;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Statement;
use Exception;

class Covid19PortugalFullUpdateDaily extends Command
{
    protected $signature = 'covid19pt:full-update-daily';

    public function handle()
    {
        $storedItemsCounter = 0;

        $csv = file_get_contents(config('services.dssg_pt_covid19.full_daily'));

        if (Storage::disk('local_data')->exists('/covid19/data.csv')) {
            Storage::disk('local_data')->delete('/covid19/data.csv');
        }

        Storage::disk('local_data')->put('/covid19/data.csv', $csv);

        $stream = Storage::disk('local_data')->readStream('/covid19/data.csv');
        $reader = Reader::createFromStream($stream);
        $reader->setHeaderOffset(0);
        $records = Statement::create()->process($reader);

        RptDaily::truncate();

        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        foreach ($records as $record) {
            $daily = new RptDaily();
            $daily->date = $record['data'];
            $daily->record_date = $record['data_dados'];
            $daily->json_raw = $record;
            $result = $daily->save();

            if ($result) {
                $storedItemsCounter++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info(sprintf("Finish. Stored %s records.", $storedItemsCounter));

        return 0;
    }

}
