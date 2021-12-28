<?php

namespace App\Console\Commands\Covid19PT;

use App\Models\RptCounty;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Statement;

class Covid19PortugalFullUpdateCounties extends Command
{
    protected $signature = 'covid19pt:full-update-counties';

    protected $client;

    public function __construct()
    {
        parent::__construct();

        $this->client = new Client();
    }

    public function handle()
    {
        $csv = file_get_contents(config('services.dssg_pt_covid19.full_counties'));

        if (Storage::disk('local_data')->exists('/covid19/data_concelhos_new.csv')) {
            Storage::disk('local_data')->delete('/covid19/data_concelhos_new.csv');
        }

        Storage::disk('local_data')->put('/covid19/data_concelhos_new.csv', $csv);

        $stream = Storage::disk('local_data')->readStream('/covid19/data_concelhos_new.csv');
        $reader = Reader::createFromStream($stream);
        $reader->setHeaderOffset(0);
        $records = Statement::create()->process($reader);


        RptCounty::truncate();

        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        $storedItemsCounter = 0;

        foreach ($records as $record) {

            $county = new RptCounty();
            $county->date = $record['data'];
            $county->name = $record['concelho'];
            $county->district = $record['distrito'];
            $county->json_raw = $record;
            $result = $county->save();

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
