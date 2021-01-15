<?php

namespace App\Console\Commands\Weather;

use GuzzleHttp\Client as HttpClient;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MongoDB\Client as MongoDBClient;

class WeatherFetchCurrent extends Command
{
    protected static $defaultName = 'weather:fetch-current';

    protected static $connection;

    protected static $db = 'suricataNotifier';

    protected static $collectionPrefix = 'weather';

    protected $httpClient;

    public function __construct()
    {
        parent::__construct();

        $this->httpClient = new HttpClient();
    }

    protected function configure()
    {
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Init db conn and set the collection
        $dbConn = $this->connectDB();
        $collection = $this->getWeatherCurrentCollection($dbConn);

        $uri = sprintf("%s/%s&key=%s",
            env('WEATHERBIT_API_BASE_URL'),
            'current?city=lisbon&country=PT&lang=pt',
            env('WEATHERBIT_API_KEY')
        );
        $response = $this->httpClient->get($uri);
        $json = $response->getBody()->getContents();
        $content = json_decode($json, true);

        $observationsData = $content['data'] ?? [];

        foreach ($observationsData as $observation) {
            $lastUpdate = new \MongoDB\BSON\UTCDateTime($observation['ts'] * 1000);
            $observation['last_update'] = $lastUpdate;

            $cursor = $collection->find(
                [],
                [
                    'limit' => 1,
                    'sort' => ['last_update' => -1],
                ]
            )->toArray();
            $result = reset($cursor);

            if (empty($result) ||
                $result->last_update < $observation['last_update']) {
                $collection->insertOne($observation);
                $this->notifyTelegram($observation);

                $msg = sprintf("%s %s - Current weather , inserted record and notification sent.",
                    now(true),
                    class_basename(self::class)
                );
                $output->writeln(PHP_EOL . "<comment>$msg</comment>" . PHP_EOL);
            } else {
                $msg = sprintf("%s %s - No new data, nothing to update.",
                    now(true),
                    class_basename(self::class)
                );
                $output->writeln(PHP_EOL . "<comment>$msg</comment>" . PHP_EOL);
            }
        }

        return Command::SUCCESS;
    }

    private function notifyTelegram(array $data)
    {
        $chatId = env('TELEGRAM_CHAT_ID');
        $uri = sprintf("%s/%s%s/%s",
            env('TELEGRAM_API_BASE_URL'),
            env('TELEGRAM_API_BOT_ENDPOINT'),
            env('TELEGRAM_BOT_TOKEN'),
            'sendMessage'
        );

        $text = $this->buildPayload($data);

        $this->httpClient->post($uri, [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'MarkdownV2'
            ]
        ]);
    }

    protected function buildPayload(array $data)
    {
        $text = sprintf("*Estado actual do tempo*");
        $text .= PHP_EOL;
        $text .= sprintf("%s %s", $data['city_name'],
            str_replace("-", "/", $data['ob_time'])
        );
        $text .= PHP_EOL;
        $text .= sprintf("%s", $data['weather']['description']);
        $text .= PHP_EOL;
        $text .= sprintf('Temperatura: %s º "Feels Like" %s º',
            str_replace(".", ",", $data['temp']),
            str_replace(".", ",", $data['app_temp'])
        );
        $text .= PHP_EOL;
        $text .= sprintf("Precipitação: %s , Nuvens: %s %s", $data['precip'], $data['clouds'], "%");
        $text .= PHP_EOL;
        $text .= sprintf("Humidade relativa: %s %s", $data['rh'], "%");
        $text .= PHP_EOL;
        $text .= sprintf("Vento: velocidade %s m/s , direção %s",
            str_replace(".", ",", $data['wind_spd']),
            str_replace("-", "\-", $data['wind_cdir_full'])
        );
        $text .= PHP_EOL;

        return $text;
    }

    private function connectDB()
    {
        if (!self::$connection) {
            self::$connection = new MongoDBClient(env('MONGO_URI'));
            self::$connection = self::$connection->{self::$db};
        }

        return self::$connection;
    }

    private function getWeatherCurrentCollection($dbConn): Collection
    {
        $collectionName = sprintf("%s_%s", self::$collectionPrefix, 'current');
        return $dbConn->{$collectionName};
    }
}
