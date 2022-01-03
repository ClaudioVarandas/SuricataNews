<?php

namespace App\Console\Commands\GamesOutbreak;

use App\Notifications\GamesOutbreakNews;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class GamespotNewsFetcher extends Command
{
    protected $signature = 'go:news:gamespot';

    public function handle()
    {
        try {

            $newsCollection = $this->getNewsFromRssFeed();
            $lastStoredNewsItem = now()->startOfDay()->timestamp;

            if(!Cache::has('gamespot_last_news_item_ts')){
                Cache::forever('gamespot_last_news_item_ts', $newsCollection->first()['published_date_ts']);
            }else{
                $lastStoredNewsItem = Cache::get('gamespot_last_news_item_ts');
            }

            $newsCollection = $newsCollection->filter(function($item) use($lastStoredNewsItem){
                return $item['published_date_ts'] > $lastStoredNewsItem;
            });

            foreach ($newsCollection->toArray() as $item){
                Notification::route('discord', config('services.discord.channels_id.news'))
                    ->notify(new GamesOutbreakNews($item));
            }

        } catch (Exception $exception) {
            $this->error($exception->getMessage());
            return 1;
        }

        return 0;
    }


    public function getNewsFromRssFeed(): Collection
    {
        $newsXml = file_get_contents(config('services.discord.feeds.game_news'));
        $newsXml = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $newsXml); // this will convert <media:content> to <mediacontent>
        $newsXml = simplexml_load_string($newsXml);
        $news = [];
        foreach ($newsXml->channel->item as $item) {
            $details = [];
            $title = (string)$item->title;
            if ($title == "This RSS feed URL is deprecated") {
                continue;
            } else {
                $details['title'] = trim($title);
                $details['description'] = trim(strip_tags((string)$item->description));
                $published_date = (string)$item->pubDate;
                $published_date = Carbon::createFromTimestamp(strtotime($published_date)); //date('Y-m-d H:i:s', strtotime($published_date));
                $details['published_date'] = $published_date->format('Y-m-d H:i:s');
                $details['published_date_ts'] = $published_date->timestamp;
                $details['url'] = $item->link;
                if (isset($item->mediacontent)) {
                    $details['image'] = $item->mediacontent["url"];
                } else {
                    $details['image'] = null;
                }
                array_push($news, $details);
            }
        }

        return collect($news);
    }
}
