<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;
use App\Models\Note;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// User's private channel for personal notifications
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence channel for collaborative editing
Broadcast::channel('note.{noteId}', function ($user, $noteId) {
    $note = Note::find($noteId);
    
    if (!$note) {
        return false;
    }
    
    $isOwner = $note->user_id === $user->id;
    $isSharedWith = $note->isSharedWithUser($user->id);
    
    // If user has access to this note, return an array with user info for presence channel
    if ($isOwner || $isSharedWith) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url, // Use the accessor
        ];
    }
    
    return false;
}); 