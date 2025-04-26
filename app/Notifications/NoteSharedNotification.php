<?php

namespace App\Notifications;

use App\Models\Note;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NoteSharedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $note;
    protected $sharedByUser;
    protected $permission;

    /**
     * Create a new notification instance.
     */
    public function __construct(Note $note, User $sharedByUser, string $permission)
    {
        $this->note = $note;
        $this->sharedByUser = $sharedByUser;
        $this->permission = $permission;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('notes.show', $this->note);
        $permissionText = $this->permission === 'edit' ? 'edit' : 'view';

        return (new MailMessage)
            ->subject($this->sharedByUser->name . ' shared a note with you')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->sharedByUser->name . ' has shared a note with you: "' . $this->note->title . '"')
            ->line('You have permission to ' . $permissionText . ' this note.')
            ->action('View Note', $url)
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Generate a relative URL instead of absolute to avoid localhost issues
        $relativeUrl = '/notes/' . $this->note->id;
        
        return [
            'note_id' => $this->note->id,
            'note_title' => $this->note->title,
            'shared_by_id' => $this->sharedByUser->id,
            'shared_by_name' => $this->sharedByUser->name,
            'permission' => $this->permission,
            'message' => $this->sharedByUser->name . ' shared a note with you: "' . $this->note->title . '"',
            'url' => $relativeUrl,
        ];
    }
} 