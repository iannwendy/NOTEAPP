<?php

namespace App\Events;

use App\Models\Note;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NoteContentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $noteId;
    public $content;
    public $userId;
    public $userName;
    public $userAvatarUrl;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(Note $note, User $user, string $content)
    {
        $this->noteId = $note->id;
        $this->content = $content;
        $this->userId = $user->id;
        $this->userName = $user->name;
        $this->userAvatarUrl = $user->avatar_url;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('note.' . $this->noteId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'note.content.updated';
    }
} 