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
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Statement;

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
        $csv = file_get_contents(config('services.dssg_pt_covid19.full_counties'));

        if (Storage::disk('local_data')->exists('/covid19/data_concelhos_new.csv')) {
            Storage::disk('local_data')->delete('/covid19/data_concelhos_new.csv');
        }

        Storage::disk('local_data')->put('/covid19/data_concelhos_new.csv', $csv);

        $stream = Storage::disk('local_data')->readStream('/covid19/data_concelhos_new.csv');
        $reader = Reader::createFromStream($stream);
        $reader->setHeaderOffset(0);
        $records = Statement::create()->process($reader);


        $updatedCountiesCollection = new Collection();
        $reportDateFormatted = '';

        foreach ($records as $record) {
            $recordDateObj = Carbon::parse($record['data']);
            $reportDateFormatted = $recordDateObj->format('Y-m-d');
            $countyRecordExists = RptCounty::where('date', $reportDateFormatted)
                ->where('name', $record['concelho'])
                ->where('district', $record['distrito'])
                ->first();

            if ($countyRecordExists) {
                continue;
            }

            $countyRptModel = RptCounty::create([
                'date' => $record['data'],
                'name' => $record['concelho'],
                'district' => $record['distrito'],
                'json_raw' => $record
            ]);

            if ($countyRptModel) {
                $updatedCountiesCollection->push($countyRptModel);
            }
        }

        $this->info(sprintf("%s new counties reported.", $updatedCountiesCollection->count()));

        if ($updatedCountiesCollection->isEmpty()) {
            return 0;
        }

        $updatedCountiesCollection
            ->sortBy(function ($model) {
                return $model->name;
            });

        foreach (array_chunk($updatedCountiesCollection->toArray(), 40, true) as $dataChunk) {
            Notification::route('telegram', config('services.telegram-bot-api.chat_id'))
                ->notify(new Covid19ReportCounty($reportDateFormatted, $dataChunk));
        }

        return 0;
    }


}
