<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class IpmaWarnings extends Notification
{
    use Queueable;

    protected array $data;

    protected string $county;

    /**
     * Create a new notification instance.
     *
     * @param array $data
     */
    public function __construct(string $county, array $data)
    {
        $this->data = $data;
        $this->county = $county;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }

    public function toTelegram($notifiable)
    {
        $content = $this->buildPayload($this->data);

        return TelegramMessage::create()
            // Markdown supported.
            ->content($content);
    }

    protected function buildPayload(array $data)
    {
        $text = sprintf("*** %s | IPMA %d avisos meteorológicos em vigor ***", $this->county, count($data));

        foreach ($data as $item) {

            $text .= PHP_EOL;
            $text .= PHP_EOL;
            $text .= sprintf("Aviso *%s* até %s ", strtoupper($item['type_level']), $item['end_time']);
            $text .= PHP_EOL;
            $text .= sprintf("%s", $item['type_name']);
            $text .= PHP_EOL;
            $text .= sprintf("%s", $item['text']);
        }

        $text .= PHP_EOL;

        return $text;
    }
}
