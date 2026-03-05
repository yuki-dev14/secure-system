<?php

namespace App\Notifications;

use App\Models\SubmittedRequirement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the Field Officer when a document is expiring within 30 days
 * or has already expired.
 */
class DocumentExpiringNotification extends Notification
{
    use Queueable;

    /**
     * @param int|null $daysRemaining  null = already expired
     */
    public function __construct(
        private readonly SubmittedRequirement $requirement,
        private readonly ?int                 $daysRemaining,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type    = ucwords(str_replace('_', ' ', $this->requirement->requirement_type));
        $bin     = $this->requirement->beneficiary?->bin ?? "ID #{$this->requirement->beneficiary_id}";
        $expired = $this->daysRemaining === null || $this->daysRemaining <= 0;
        $expDate = $this->requirement->expiration_date?->format('M d, Y') ?? 'Unknown';

        $subject = $expired
            ? "⚠ Document Expired — {$type} — Action Required"
            : "⏳ Document Expiring in {$this->daysRemaining} Day(s) — {$type}";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},");

        if ($expired) {
            $message
                ->line("A document submitted for beneficiary **{$bin}** has **expired** and must be renewed immediately.")
                ->line("**Document Type:** {$type}")
                ->line("**File:** {$this->requirement->file_name}")
                ->line("**Expired On:** {$expDate}");
        } else {
            $message
                ->line("A document submitted for beneficiary **{$bin}** will expire in **{$this->daysRemaining} day(s)**.")
                ->line("**Document Type:** {$type}")
                ->line("**File:** {$this->requirement->file_name}")
                ->line("**Expiry Date:** {$expDate}");
        }

        return $message
            ->action('Renew Document', url("/requirements/{$this->requirement->beneficiary_id}"))
            ->line('Please upload a renewed document before the expiry date to maintain compliance eligibility.')
            ->salutation('The SECURE System');
    }

    public function toDatabase(object $notifiable): array
    {
        $expired = $this->daysRemaining === null || $this->daysRemaining <= 0;

        return [
            'type'             => $expired ? 'document_expired' : 'document_expiring',
            'requirement_id'   => $this->requirement->id,
            'requirement_type' => $this->requirement->requirement_type,
            'beneficiary_id'   => $this->requirement->beneficiary_id,
            'file_name'        => $this->requirement->file_name,
            'expiration_date'  => $this->requirement->expiration_date?->toDateString(),
            'days_remaining'   => $this->daysRemaining,
            'message'          => $expired
                ? ucwords(str_replace('_', ' ', $this->requirement->requirement_type)) . ' has expired — please renew.'
                : ucwords(str_replace('_', ' ', $this->requirement->requirement_type)) . " expires in {$this->daysRemaining} day(s).",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
