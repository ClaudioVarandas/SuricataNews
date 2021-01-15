<?php

namespace App\Console\Commands\Covid19PT;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Console\Command;

class Covid19PortugalCountyUpdate extends Command
{
    protected $signature = 'covid19pt:county-update';

    protected $client;

    public function __construct()
    {
        parent::__construct();

        $this->client = new HttpClient();
    }

    protected function handle()
    {
        $uri = env('DATASOURCE_COUNTIES');

        $csvData = file_get_contents($uri);
        $rows = explode("\n", $csvData);

        $dbConn = $this->connectDB();
        $collection = $this->getReportsCountyCollection($dbConn);
        $collection->drop();

        $insertsCount = 0;

        foreach ($rows as $key => $row) {

            $item = new \stdClass();

            if ($key === 0) {
                $counties = str_getcsv($row);
                unset($counties[0]);
                continue;
            } else {
                $line = str_getcsv($row);

                foreach ($line as $subKey => $cell) {

                    if ($subKey === 0) {
                        if (empty($cell)) {
                            continue;
                        }
                        $dateObj = \DateTime::createFromFormat('d-m-Y', $cell);
                        $ts = $dateObj->getTimestamp();
                        $item->data = new \MongoDB\BSON\UTCDateTime($ts * 1000);
                        continue;
                    }
                    $county = $counties[$subKey];
                    $item->{$county} = (int)$cell;
                }

                $result = $collection->insertOne($item);

                if ($result->isAcknowledged()) {
                    $insertsCount += $result->getInsertedCount();
                }
            }
        }

        $msg = sprintf("%s %s - Inserted %d documents.",
            now(true),
            class_basename(self::class),
            $insertsCount
        );
        $output->writeln("<info>$msg</info>");


        $cursor = $collection->find(
            [],
            [
                'limit' => 2,
                'sort' => ['data' => -1],
            ]
        );

        $tempArr = [];
        $responseData = [];
        /** @var BSONDocument $document */
        foreach ($cursor as $key => $document) {
            $tempArr[] = $document->getArrayCopy();
            $counties = array_keys($document->getArrayCopy());
        }
        unset($counties[0], $counties[1]);

        foreach ($counties as $county) {
            $responseData[$county] = sprintf("%s (+%s)",
                $tempArr[0][$county],
                $tempArr[0][$county] - $tempArr[1][$county]
            );
        }

        /** @var UTCDateTime $date */
        $date = $tempArr[0]['data'];

        $lastUpdate = $date->toDateTime();
        $now = new \DateTime('now');

        if (!($lastUpdate < $now)) {
            $this->notifyTelegram($lastUpdate, $responseData);
            $msg = sprintf("%s %s - Notifications sent!", now(true), class_basename(self::class));
            $output->writeln(PHP_EOL . "<comment>$msg</comment>" . PHP_EOL);
        } else {
            $msg = sprintf("%s %s - No new data, no notifications sent.", now(true), class_basename(self::class));
            $output->writeln(PHP_EOL . "<comment>$msg</comment>" . PHP_EOL);
        }

        return Command::SUCCESS;
    }


    private function connectDB()
    {
        if (!self::$connection) {
            self::$connection = new MongoDBClient(env('MONGO_URI'));
            self::$connection = self::$connection->{self::$db};
        }

        return self::$connection;
    }

    private function notifyTelegram(\DateTimeInterface $date, array $data)
    {

        $chatId = env('TELEGRAM_CHAT_ID');
        $uri = sprintf("%s/%s%s/%s",
            env('TELEGRAM_API_BASE_URL'),
            env('TELEGRAM_API_BOT_ENDPOINT'),
            env('TELEGRAM_BOT_TOKEN'),
            'sendMessage'
        );

        foreach (array_chunk($data, 50, true) as $dataChunk) {
            $text = $this->buildPayload($date, $dataChunk);

            $this->client->post($uri, [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'markdown'
                ]
            ]);
        }
    }

    protected function buildPayload(\DateTimeInterface $date, array $data)
    {
        $text = '*COVID19 | Dados por concelho* ' . PHP_EOL . PHP_EOL .
            'Ultima actualizaçao: ' . $date->format('d-m-Y') . PHP_EOL . PHP_EOL;

        foreach ($data as $county => $value) {
            $text .= sprintf("%s %s", $county, $value) . PHP_EOL;
        }

        return $text;
    }

    private function getReportsCountyCollection($dbConn)
    {
        $collectionName = sprintf("%s_%s", self::$collectionPrefix, 'reports_county');
        return $dbConn->{$collectionName};
    }


}
