<?php

namespace App\Console\Commands\Weather;

use GuzzleHttp\Client as HttpClient;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MongoDB\Client as MongoDBClient;

class WeatherForecastNextDays extends Command
{
    protected static $defaultName = 'weather:forecast-days';

    protected static $connection;

    protected static $db = 'suricataNotifier';

    protected static $collectionPrefix = 'weather';

    protected static $daysForecast = 3;

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

        $uri = sprintf("%s/%s?days=%d&city=lisbon&country=PT&lang=pt&key=%s",
            env('WEATHERBIT_API_BASE_URL'),
            'forecast/daily',
            self::$daysForecast,
            env('WEATHERBIT_API_KEY')
        );
        $response = $this->httpClient->get($uri);
        $json = $response->getBody()->getContents();
        $content = json_decode($json, true);

        if (empty($content['data'])) {
            return Command::SUCCESS;
        }

        $this->notifyTelegram($content);

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

    protected function buildPayload(array $forecastData)
    {
        $weekDays = [
            'Domingo',
            'Segunda',
            'Terça',
            'Quarta',
            'Quinta',
            'Sexta',
            'Sábado',
        ];

        $text = sprintf("*Previsão estado tempo para os próximos %d dias*", self::$daysForecast);
        $text .= PHP_EOL;
        $text .= sprintf("_%s_", $forecastData['city_name']);
        $text .= PHP_EOL;
        $text .= PHP_EOL;

        foreach ($forecastData['data'] as $forecast) {

            $text .= PHP_EOL;
            $forecastValidDate = date_create_from_format('Y-m-d', $forecast['valid_date']);
            $weekDayIndex = date('w', $forecastValidDate->getTimestamp());

            $text .= sprintf("*%s , %s*",
                str_replace("-","\-",$forecast['valid_date'])
                ,
                $weekDays[$weekDayIndex]
            );
            $text .= PHP_EOL;
            $text .= sprintf("%s", $forecast['weather']['description']);
            $text .= PHP_EOL;
            $text .= sprintf('Temperatura: min %s º \(%s º\) , max min %s º \(%s º\)',
                $forecast['min_temp'],
                $forecast['app_min_temp'],
                $forecast['max_temp'],
                $forecast['app_max_temp']
            );
            $text .= PHP_EOL;
            $text .= sprintf("Precipitação: %s , Nuvens: %s %s", $forecast['precip'], $forecast['clouds'], "%");
            $text .= PHP_EOL;
            $text .= sprintf("Humidade relativa: %s %s", $forecast['rh'], "%");
            $text .= PHP_EOL;
            $text .= sprintf("Vento: velocidade %s m/s , direção %s",
                $forecast['wind_spd'],
                str_replace("-", "\-", $forecast['wind_cdir_full'])
            );
            $text .= PHP_EOL;

        }

        $text = str_replace(".", "\.", $text);

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
        $collectionName = sprintf("%s_%s", self::$collectionPrefix, 'forecast_days');
        return $dbConn->{$collectionName};
    }
}
