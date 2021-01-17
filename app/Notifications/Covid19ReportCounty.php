<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class Covid19ReportCounty extends Notification
{
    use Queueable;

    protected $data;

    protected $dateUpdate;

    /**
     * Create a new notification instance.
     *
     * @param string $dateUpdate
     * @param array $data
     */
    public function __construct(string $dateUpdate, array $data)
    {
        $this->dateUpdate = $dateUpdate;
        $this->data = $data;
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
        $content = $this->buildPayload();

        return TelegramMessage::create()
            // Markdown supported.
            ->content($content);
    }

    protected function buildPayload(): string
    {
        $text = sprintf("*COVID19 | Dados por concelho* \nUltima actualizaçao: %s\n\n", $this->dateUpdate);

        foreach ($this->data as $county) {
            $rawData = $county['json_raw'];
            $text .= sprintf("*%s [ %s ]*\n",
                $rawData['concelho'],
                $rawData['distrito']
            );
            $text .= sprintf("Risco _%s_ %s casos.\n", strtoupper($rawData['incidencia_risco']), $rawData['confirmados_14']);
            $text .= sprintf("Pop. %s | Incidência: %s / 100k\n",
                $rawData['population'],
                $rawData['incidencia']
            );
        }

        return $text;
    }
}
