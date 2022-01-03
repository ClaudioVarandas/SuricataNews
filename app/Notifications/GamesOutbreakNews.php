<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Discord\Discord;
use NotificationChannels\Discord\DiscordChannel;
use NotificationChannels\Discord\DiscordMessage;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class GamesOutbreakNews extends Notification
{
    use Queueable;

    protected array $data;

    /**
     * Create a new notification instance.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function via($notifiable)
    {
        return [DiscordChannel::class];
    }

    public function toDiscord($notifiable): DiscordMessage
    {
        $descriptionArray = str_split($this->data['description'], 250);

        $embedData = [
            'title' => $this->data['title'],
            'description' => $descriptionArray[0] . ' (...)',
            'url' => (string)$this->data['url'],
            'timestamp' => Carbon::createFromTimestamp($this->data['published_date_ts'])->toIso8601String(),
        ];

        return DiscordMessage::create()->embed($embedData);
    }
}
