<?php

namespace App\Notifications;

use App\Models\SubmittedRequirement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the Field Officer who submitted a document when it is rejected.
 */
class DocumentRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SubmittedRequirement $requirement,
        private readonly string               $rejectorName,
        private readonly string               $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $type = ucwords(str_replace('_', ' ', $this->requirement->requirement_type));
        $bin  = $this->requirement->beneficiary?->bin ?? "ID #{$this->requirement->beneficiary_id}";

        return (new MailMessage)
            ->subject("❌ Document Rejected — {$type} — Action Required")
            ->greeting("Hello {$notifiable->name},")
            ->line("A document submitted for beneficiary **{$bin}** has been **rejected** and requires your attention.")
            ->line("**Document Type:** {$type}")
            ->line("**File:** {$this->requirement->file_name}")
            ->line("**Rejected By:** {$this->rejectorName}")
            ->line("**Reason:** {$this->reason}")
            ->line("**Rejected At:** " . now()->format('M d, Y h:i A'))
            ->action('Resubmit Document', url("/requirements/{$this->requirement->beneficiary_id}"))
            ->line('Please resubmit a corrected document at your earliest convenience to avoid delays in the beneficiary\'s cash grant processing.')
            ->salutation('The SECURE System');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'             => 'document_rejected',
            'requirement_id'   => $this->requirement->id,
            'requirement_type' => $this->requirement->requirement_type,
            'beneficiary_id'   => $this->requirement->beneficiary_id,
            'file_name'        => $this->requirement->file_name,
            'rejected_by'      => $this->rejectorName,
            'reason'           => $this->reason,
            'message'          => ucwords(str_replace('_', ' ', $this->requirement->requirement_type)) . ' document rejected: ' . $this->reason,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
