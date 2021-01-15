<?php

namespace App\Console\Commands\Weather;

use GuzzleHttp\Client as HttpClient;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MongoDB\Client as MongoDBClient;

class WeatherFetchSevere extends Command
{
    protected static $defaultName = 'weather:fetch-severe';

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
            'alerts?city=lisbon&country=PT&lang=pt',
            env('WEATHERBIT_API_KEY')
        );
        $response = $this->httpClient->get($uri);
        $json = $response->getBody()->getContents();
        $content = json_decode($json, true);

        if (!empty($content['alerts'])) {
            foreach ($content['alerts'] as $alert) {
                $now = new \MongoDB\BSON\UTCDateTime(now()->getTimestamp() * 1000);
                $nowISO8601 = substr($now->toDateTime()->format(\DateTime::ISO8601),0,-5);
                $cursor = $collection->find(
                    [
                        'effective_utc' => ['$lte' => $nowISO8601],
                        'expires_utc' => ['$gte' => $nowISO8601],
                        'severity' => $alert['severity'],
                        'title' => $alert['title'],
                    ],
                    [
                        'limit' => 1
                    ]
                )->toArray();

                $result = reset($cursor);

                if(empty($result)){
                    $collection->insertOne($alert);
                    $this->notifyTelegram($alert);

                    $msg = sprintf("%s %s - Severe weather , inserted record and notification sent.",
                        now(true),
                        class_basename(self::class)
                    );
                    $output->writeln(PHP_EOL . "<comment>$msg</comment>" . PHP_EOL);
                }
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
        $dateStart = new \DateTime($data['effective_utc'], new \DateTimeZone('UTC'));
        $dateEnds = new \DateTime($data['expires_utc'], new \DateTimeZone('UTC'));
        $severity = [
            'advisory' => 'Amarelo',
            'watch' => 'Laranja',
            'warning' => 'Vermelho'
        ];

        $text = sprintf("*Avisos meteorológicos*");
        $text .= PHP_EOL;
        $text .= sprintf("_%s_", implode(" - ", $data['regions']));
        $text .= PHP_EOL;
        $text .= sprintf("*%s*", $severity[strtolower($data['severity'])]);
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= sprintf("Validade : %sdesde *%s* até *%s*",
            PHP_EOL,
            $dateStart->format('d/m/Y H:i:s'),
            $dateEnds->format('d/m/Y H:i:s')
        );
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= str_replace(".", "\.", $data['title']);
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= str_replace(".", "\.", $data['description']);
        $text .= PHP_EOL;
        $text .= sprintf("[Link com \+info](%s)", str_replace(".", "\.", $data['uri']));
        $text .= PHP_EOL;

        $text = str_replace("(","\(",$text);
        $text = str_replace(")","\)",$text);

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
        $collectionName = sprintf("%s_%s", self::$collectionPrefix, 'severe');
        return $dbConn->{$collectionName};
    }
}
