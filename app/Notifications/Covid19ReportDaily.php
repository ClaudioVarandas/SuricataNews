<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class Covid19ReportDaily extends Notification
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(array $data)
    {
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
        $content = $this->buildPayload($this->data);

        return TelegramMessage::create()
            // Markdown supported.
            ->content($content);
    }

    protected function buildPayload(array $data)
    {
        $text = sprintf("*COVID19 | Data do relatório* (resumo): %s", $data['data']);
        $text .= PHP_EOL;
        $text .= PHP_EOL;
        $text .= sprintf("- Novos casos confirmados: %s (%s)", $data['confirmados_novos'], $data['confirmados_novos_var']);
        $text .= PHP_EOL;
        $text .= sprintf("- Total de casos confirmados: %s", $data['confirmados']);
        $text .= PHP_EOL;
        $text .= sprintf("- Total de internados: %s", $data['internados']);
        $text .= PHP_EOL;
        $text .= sprintf("- Total de internados UCI: %s", $data['internados_uci']);
        $text .= PHP_EOL;
        $text .= sprintf("- Total de casos activos: %s", $data['ativos']);
        $text .= PHP_EOL;
        $text .= sprintf("- Total de óbitos: %s (%s)", $data['obitos'], $data['obitos_var']);
        $text .= PHP_EOL;

        return $text;
    }
}
