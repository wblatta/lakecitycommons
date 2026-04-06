<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistAvailable extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $resourceTitle,
        public readonly string $resourceUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message'  => "\"{$this->resourceTitle}\" is now available.",
            'url'      => $this->resourceUrl,
            'resource' => $this->resourceTitle,
        ];
    }
}
