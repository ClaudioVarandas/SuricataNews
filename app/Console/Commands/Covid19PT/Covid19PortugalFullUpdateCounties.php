<?php

namespace App\Console\Commands\Covid19PT;

use App\Models\RptCounty;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

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
        $vostBaseURl = config('services.vost_covid19_rest_api.base_url');
        $uri = sprintf('%s/%s', $vostBaseURl, 'get_full_dataset_counties');
        $response = $this->client->get($uri);
        $fullDataArray = json_decode($response->getBody(), true);

        RptCounty::truncate();

        $bar = $this->output->createProgressBar(count($fullDataArray));
        $bar->start();

        $storedItemsCounter = 0;
        foreach ($fullDataArray as $item) {

            try {
                $county = new RptCounty();
                $county->date = $item['data'];
                $county->name = $item['concelho'];
                $county->district = $item['distrito'];
                $county->json_raw = $item;
                $result = $county->save();

                if ($result) {
                    $storedItemsCounter++;
                }
                $bar->advance();

            } catch (\Throwable $t) {
                $this->error($t->getMessage() . PHP_EOL);
            }
        }
        $bar->finish();
        $this->newLine();
        $this->info(sprintf("Finish. Stored %s records.", $storedItemsCounter));

        return Command::SUCCESS;
    }

}
