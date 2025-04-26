<?php

use Illuminate\Support\Facades\Broadcast;
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

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel for note collaboration - requires user to be note owner or have edit permission
Broadcast::channel('note.{noteId}', function ($user, $noteId) {
    $note = Note::findOrFail($noteId);
    
    // Get avatar URL from user using the accessor
    $avatarUrl = $user->avatar_url;
    
    // Allow if user is the owner
    if ($user->id === $note->user_id) {
        return [
            'id' => $user->id, 
            'name' => $user->name,
            'avatar_url' => $avatarUrl
        ];
    }
    
    // Allow if user has edit permission to this note
    if ($note->isSharedWithUser($user->id) && $note->getPermissionForUser($user->id) === 'edit') {
        return [
            'id' => $user->id, 
            'name' => $user->name,
            'avatar_url' => $avatarUrl
        ];
    }
    
    return false;
}); 