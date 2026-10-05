<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListingExpiryNotification extends Notification
{
    public function __construct(public int $listingId, public string $listingTitle, public string $deadline, public bool $reminder) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title())->line($this->body())
            ->action('Renew your listing', route('dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->body(), 'listing_id' => $this->listingId];
    }

    private function title(): string
    {
        return $this->reminder ? 'Renew your listing before deletion' : 'Your listing has expired';
    }

    private function body(): string
    {
        return '“'.$this->listingTitle.'” is inactive after one year. Renew from My listings for admin review. '
            .'Without renewal, it is scheduled for deletion on '.$this->deadline.'.';
    }
}
