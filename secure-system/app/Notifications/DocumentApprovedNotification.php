<?php

namespace App\Notifications;

use App\Models\SubmittedRequirement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the Field Officer who submitted a document when it is approved.
 */
class DocumentApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SubmittedRequirement $requirement,
        private readonly string               $approverName,
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
            ->subject("✅ Document Approved — {$type}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A document you submitted for beneficiary **{$bin}** has been **approved**.")
            ->line("**Document Type:** {$type}")
            ->line("**File:** {$this->requirement->file_name}")
            ->line("**Approved By:** {$this->approverName}")
            ->line("**Approved At:** " . now()->format('M d, Y h:i A'))
            ->action('View Requirements', url("/requirements/{$this->requirement->beneficiary_id}"))
            ->line('This document will now count toward compliance calculations.')
            ->salutation('The SECURE System');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'             => 'document_approved',
            'requirement_id'   => $this->requirement->id,
            'requirement_type' => $this->requirement->requirement_type,
            'beneficiary_id'   => $this->requirement->beneficiary_id,
            'file_name'        => $this->requirement->file_name,
            'approved_by'      => $this->approverName,
            'message'          => ucwords(str_replace('_', ' ', $this->requirement->requirement_type)) . ' document approved.',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
