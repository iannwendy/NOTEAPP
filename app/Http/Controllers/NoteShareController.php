<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteShare;
use App\Models\User;
use App\Notifications\NoteSharedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class NoteShareController extends Controller
{
    /**
     * Show the sharing settings for a note.
     */
    public function show(Note $note)
    {
        // Ensure the note belongs to the authenticated user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $shares = $note->shares()->with('user')->get();
        
        return view('notes.shares.show', compact('note', 'shares'));
    }

    /**
     * Show the form for sharing a note.
     */
    public function create(Note $note)
    {
        // Ensure the note belongs to the authenticated user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        return view('notes.shares.create', compact('note'));
    }

    /**
     * Store a newly created share.
     */
    public function store(Request $request, Note $note)
    {
        // Ensure the note belongs to the authenticated user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // First validate the email format
        $request->validate([
            'email' => 'required|email',
            'permission' => ['required', Rule::in(['read', 'edit'])],
        ]);

        $email = $request->input('email');
        
        try {
            // Find the user to share with
            $user = User::where('email', $email)->first();
            
            // Check if the user exists
            if (!$user) {
                return back()->withErrors([
                    'email' => 'No user found with this email address. Only registered users can be shared with.'
                ])->withInput();
            }
            
            // Don't allow sharing with yourself
            if ($user->id === Auth::id()) {
                return back()->withErrors([
                    'email' => 'You cannot share a note with yourself.'
                ])->withInput();
            }
            
            // Check if the note is already shared with this user
            if ($note->isSharedWithUser($user->id)) {
                return back()->withErrors([
                    'email' => 'This note is already shared with this user.'
                ])->withInput();
            }
            
            $permission = $request->input('permission');
            
            // Create the share
            $note->sharedWith()->attach($user->id, [
                'permission' => $permission
            ]);
            
            // Send notification to the user
            $user->notify(new NoteSharedNotification($note, Auth::user(), $permission));
            
            Log::info('Note shared successfully', [
                'note_id' => $note->id,
                'shared_with' => $user->id,
                'permission' => $permission,
                'notification_sent' => true
            ]);
            
            return redirect()->route('notes.shares.show', $note)
                ->with('success', 'Note shared successfully with ' . $user->email . '. They have been notified by email.');
                
        } catch (\Exception $e) {
            Log::error('Error sharing note', [
                'note_id' => $note->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors([
                'error' => 'An error occurred while sharing the note: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * Update a share's permission.
     */
    public function update(Request $request, Note $note, NoteShare $share)
    {
        // Ensure the note belongs to the authenticated user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        // Ensure the share belongs to the note
        if ($share->note_id !== $note->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'permission' => ['required', Rule::in(['read', 'edit'])],
        ]);

        try {
            $share->update([
                'permission' => $validated['permission']
            ]);
            
            Log::info('Share permission updated', [
                'note_id' => $note->id,
                'share_id' => $share->id,
                'permission' => $validated['permission']
            ]);
            
            return redirect()->route('notes.shares.show', $note)
                ->with('success', 'Share permission updated successfully.');
                
        } catch (\Exception $e) {
            Log::error('Error updating share permission', [
                'note_id' => $note->id,
                'share_id' => $share->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'An error occurred while updating the share: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove a share.
     */
    public function destroy(Note $note, NoteShare $share)
    {
        // Ensure the note belongs to the authenticated user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        // Ensure the share belongs to the note
        if ($share->note_id !== $note->id) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $share->delete();
            
            Log::info('Share removed', [
                'note_id' => $note->id,
                'share_id' => $share->id,
                'shared_with' => $share->user_id
            ]);
            
            return redirect()->route('notes.shares.show', $note)
                ->with('success', 'Share removed successfully.');
                
        } catch (\Exception $e) {
            Log::error('Error removing share', [
                'note_id' => $note->id,
                'share_id' => $share->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'An error occurred while removing the share: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Show shared notes for the current user.
     */
    public function index()
    {
        $sharedNotes = Auth::user()->sharedNotes()->with('user')->get();
        
        return view('notes.shares.index', compact('sharedNotes'));
    }
}
