<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Auth::user()->notes();
        
        // Filter by label if label_id is provided
        if ($request->has('label_id') && is_numeric($request->label_id)) {
            $label = Auth::user()->labels()->findOrFail($request->label_id);
            $query->whereHas('labels', function($q) use ($label) {
                $q->where('labels.id', $label->id);
            });
        }
        
        // Get notes sorted by pinned status first, then by creation time
        $notes = $query->orderBy('pinned', 'desc')
            ->orderBy('pinned_at', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();
        
        // Get all labels for the filter dropdown
        $labels = Auth::user()->labels()->orderBy('name')->get();
        $selectedLabel = $request->has('label_id') ? (int)$request->label_id : null;
            
        return view('notes.index', compact('notes', 'labels', 'selectedLabel'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get the user's default color preference if available
        $defaultColor = null;
        if (Auth::user()->preferences && isset(Auth::user()->preferences['note_color'])) {
            $defaultColor = Auth::user()->preferences['note_color'];
            Log::info('Using default note color from preferences: ' . $defaultColor);
        } else {
            Log::info('No default note color found in preferences, using #ffffff');
            $defaultColor = '#ffffff';
        }
        
        return view('notes.create', compact('defaultColor'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|max:255',
                'content' => 'required',
                'color' => 'nullable|string|max:20',
                'temp_id' => 'nullable|string',
            ]);

            // First check: Find by temp_id if provided
            $existingNote = null;
            if ($request->has('temp_id') && !empty($request->temp_id)) {
                Log::info('Looking for existing note with temp_id: ' . $request->temp_id);
                $existingNote = Auth::user()->notes()->where('id', $request->temp_id)->first();
                
                if ($existingNote) {
                    Log::info('Found existing note by temp_id: ' . $existingNote->id);
                }
            }
            
            // Second check: If no note found by temp_id and this looks like a duplicate submission,
            // look for recently created notes with same title and content (duplicates)
            if (!$existingNote) {
                // First try exact match on title and content
                $recentNote = Auth::user()->notes()
                    ->where('title', $validated['title'])
                    ->where('content', $validated['content'])
                    ->where('created_at', '>', now()->subMinutes(10))
                    ->latest()
                    ->first();
                    
                if ($recentNote) {
                    Log::info('Found exact duplicate note created in last 10 minutes: ' . $recentNote->id);
                    $existingNote = $recentNote;
                } else {
                    // If no exact match, try just matching on title for very recent notes (30 seconds)
                    // This helps when auto-save creates a note and then user immediately edits and saves
                    $veryRecentNote = Auth::user()->notes()
                        ->where('title', $validated['title'])
                        ->where('created_at', '>', now()->subSeconds(30))
                        ->latest()
                        ->first();
                        
                    if ($veryRecentNote) {
                        Log::info('Found very recent note with same title created in last 30 seconds: ' . $veryRecentNote->id);
                        $existingNote = $veryRecentNote;
                    }
                }
            }

            // If no color is provided, use user's default color preference
            if (empty($validated['color']) || $validated['color'] === '#ffffff' || $validated['color'] === '#fff') {
                if (Auth::user()->preferences && isset(Auth::user()->preferences['note_color'])) {
                    $userPreferenceColor = Auth::user()->preferences['note_color'];
                    $validated['color'] = $userPreferenceColor;
                    Log::info('Applied default color preference: ' . $validated['color']);
                } else {
                    // Fallback to a default color if preference not set
                    $validated['color'] = '#ffffff';
                    Log::info('No color preference found, using default white: #ffffff');
                }
            } else {
                Log::info('Using provided color: ' . $validated['color']);
            }

            // Create or update the note
            if ($existingNote) {
                // Clean validated data for update (remove temp_id)
                unset($validated['temp_id']);
                
                $existingNote->update($validated);
                $note = $existingNote;
                Log::info('Updated existing note: ' . $note->id . ' with color: ' . $note->color);
            } else {
                // Clean validated data for create (remove temp_id)
                unset($validated['temp_id']);
                
                $note = Auth::user()->notes()->create($validated);
                Log::info('Created new note: ' . $note->id . ' with color: ' . $note->color);
            }

            // Check if this is an AJAX request (auto-save)
            if ($request->ajax() || $request->has('_autosave')) {
                Log::info('Auto-save response for note: ' . $note->id);
                return response()->json([
                    'success' => true,
                    'message' => 'Note saved successfully',
                    'note' => $note
                ]);
            }

            return redirect()->route('notes.show', $note)
                ->with('success', 'Note created successfully.');
        } catch (\Exception $e) {
            Log::error('Error creating note: ' . $e->getMessage());
            
            if ($request->ajax() || $request->has('_autosave')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save note: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->withInput()->withErrors(['error' => 'Failed to create note: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Note $note, Request $request)
    {
        // Ensure the note belongs to the authenticated user or is shared with them
        $isOwner = $note->user_id === Auth::id();
        $isShared = $note->isSharedWithUser(Auth::id());
        
        if (!$isOwner && !$isShared) {
            abort(403, 'Unauthorized action.');
        }

        // Handle AJAX request for labels data
        if ($request->ajax() || $request->has('_labels')) {
            return response()->json([
                'success' => true,
                'labels' => $note->labels
            ]);
        }

        // Check if note is password protected and user hasn't verified (only for owner)
        $hasVerified = session()->has('note_verified_' . $note->id);
        if ($isOwner && $note->is_password_protected && !$hasVerified) {
            return view('notes.password-prompt', compact('note'));
        }

        // Get user's permission if it's a shared note
        $permission = $isShared ? $note->getPermissionForUser(Auth::id()) : 'owner';
        
        return view('notes.show', compact('note', 'permission', 'isOwner', 'isShared'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Note $note)
    {
        // Ensure the note belongs to the authenticated user or is shared with edit permission
        $isOwner = $note->user_id === Auth::id();
        $isSharedWithEditPermission = $note->isSharedWithUser(Auth::id()) && 
                                      $note->getPermissionForUser(Auth::id()) === 'edit';
        
        if (!$isOwner && !$isSharedWithEditPermission) {
            abort(403, 'Unauthorized action.');
        }

        // Check if note is password protected and user hasn't verified (only for owner)
        $hasVerified = session()->has('note_verified_' . $note->id);
        if ($isOwner && $note->is_password_protected && !$hasVerified) {
            return view('notes.password-prompt', compact('note'));
        }

        return view('notes.edit', compact('note'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Note $note)
    {
        // Ensure the note belongs to the authenticated user or is shared with edit permission
        $isOwner = $note->user_id === Auth::id();
        $isSharedWithEditPermission = $note->isSharedWithUser(Auth::id()) && 
                                      $note->getPermissionForUser(Auth::id()) === 'edit';
        
        if (!$isOwner && !$isSharedWithEditPermission) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $validated = $request->validate([
                'title' => 'required|max:255',
                'content' => 'required',
                'color' => 'nullable|string|max:20',
            ]);

            $note->update($validated);
            
            // Log request info for debugging
            if ($request->has('_autosave')) {
                Log::info('Auto-save update for note: ' . $note->id . ' with _autosave=' . $request->input('_autosave'));
            }

            // Check if this is an AJAX request (auto-save)
            if ($request->ajax() || $request->has('_autosave')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Note updated successfully',
                    'note' => $note
                ]);
            }

            return redirect()->route('notes.show', $note)
                ->with('success', 'Note updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating note: ' . $e->getMessage());
            
            if ($request->ajax() || $request->has('_autosave')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update note: ' . $e->getMessage()
                ], 500);
            }
            
            return back()->withInput()->withErrors(['error' => 'Failed to update note: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Note $note)
    {
        // Ensure the note belongs to the authenticated user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        // Check if note is password protected and user hasn't verified
        $hasVerified = session()->has('note_verified_' . $note->id);
        if ($note->is_password_protected && !$hasVerified) {
            return view('notes.password-prompt', compact('note'))->with('warning', 'You must enter the password before deleting this note.');
        }

        $note->delete();

        return redirect()->route('notes.index')
            ->with('success', 'Note deleted successfully.');
    }

    /**
     * Toggle the pinned status of a note.
     */
    public function togglePin(Note $note)
    {
        // Ensure the note belongs to the authenticated user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        try {
            // Toggle the pinned status
            $note->pinned = !$note->pinned;
            
            // Update pinned_at timestamp if being pinned
            if ($note->pinned) {
                $note->pinned_at = now();
            } else {
                $note->pinned_at = null;
            }
            
            $note->save();
            
            // Respond to AJAX requests
            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'pinned' => $note->pinned,
                    'message' => $note->pinned ? 'Note pinned successfully' : 'Note unpinned successfully'
                ]);
            }
            
            return redirect()->route('notes.index')
                ->with('success', $note->pinned ? 'Note pinned successfully' : 'Note unpinned successfully');
                
        } catch (\Exception $e) {
            Log::error('Error toggling pin status: ' . $e->getMessage());
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update pin status: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->withErrors(['error' => 'Failed to update pin status: ' . $e->getMessage()]);
        }
    }

    /**
     * Verify the password for a password-protected note.
     */
    public function verifyPassword(Request $request, Note $note)
    {
        // Ensure the note belongs to the authenticated user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'password' => 'required|string',
        ]);

        if ($note->checkPassword($request->password)) {
            // Store in session that the user has verified for this note
            session(['note_verified_' . $note->id => true]);
            
            return redirect()->route('notes.show', $note)
                ->with('success', 'Password verified successfully.');
        }

        return back()->withErrors(['password' => 'Incorrect password. Please try again.']);
    }

    /**
     * Show form to manage note password protection.
     */
    public function showPasswordProtection(Note $note)
    {
        // Ensure the note belongs to the authenticated user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        return view('notes.password-protection', compact('note'));
    }

    /**
     * Update the password protection settings for a note.
     */
    public function updatePasswordProtection(Request $request, Note $note)
    {
        // Ensure the note belongs to the authenticated user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        Log::info('Password protection update request', [
            'note_id' => $note->id, 
            'is_protected' => $note->is_password_protected,
            'request_data' => $request->except(['password', 'password_confirmation', 'current_password'])
        ]);

        // Prepare validation rules
        $rules = [
            'enable_protection' => 'required|boolean',
        ];

        // Validate current password if note is already protected or if protection is being turned off
        if ($note->is_password_protected) {
            $rules['current_password'] = 'required|string';
        }

        // When enabling protection, require password and confirmation
        if ($request->enable_protection == 1) {
            // If changing password or newly enabling protection
            if (!$note->is_password_protected || ($note->is_password_protected && !empty($request->password))) {
                $rules['password'] = 'required|string|min:6|confirmed';
                $rules['password_confirmation'] = 'required|string|min:6';
            }
        }

        $validated = $request->validate($rules);

        Log::info('Validated data', ['enable_protection' => $validated['enable_protection']]);

        // If note is currently password-protected, verify current password
        if ($note->is_password_protected) {
            if (!$note->checkPassword($validated['current_password'])) {
                Log::warning('Incorrect password provided for note', ['note_id' => $note->id]);
                return back()->withInput()
                    ->withErrors(['current_password' => 'Current password is incorrect.']);
            }
            Log::info('Current password verified successfully');
        }

        // Convert to boolean to ensure proper handling
        $enableProtection = (bool)$validated['enable_protection'];
        
        $note->is_password_protected = $enableProtection;
        
        // Only update password if protection is enabled and new password is provided
        if ($enableProtection && !empty($validated['password'])) {
            $note->password = $validated['password'];
            Log::info('Setting new password for note', ['note_id' => $note->id]);
        } elseif (!$enableProtection) {
            $note->password = null;
            Log::info('Removing password protection from note', ['note_id' => $note->id]);
        }
        
        $note->save();
        Log::info('Note updated successfully', [
            'note_id' => $note->id,
            'is_protected' => $note->is_password_protected
        ]);
        
        // If protection was disabled, remove the verification from session
        if (!$enableProtection) {
            session()->forget('note_verified_' . $note->id);
            Log::info('Removed password verification from session', ['note_id' => $note->id]);
        }

        return redirect()->route('notes.show', $note)
            ->with('success', $enableProtection ? 'Password protection enabled.' : 'Password protection disabled.');
    }

    /**
     * Handle real-time collaborative updates for a note.
     */
    public function realTimeUpdate(Request $request, Note $note)
    {
        // Ensure the note belongs to the authenticated user or is shared with edit permission
        $isOwner = $note->user_id === Auth::id();
        $isSharedWithEditPermission = $note->isSharedWithUser(Auth::id()) && 
                                    $note->getPermissionForUser(Auth::id()) === 'edit';
        
        if (!$isOwner && !$isSharedWithEditPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        try {
            // Validate and determine what's being updated
            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'content' => 'nullable|string',
                'cursor_position' => 'nullable|integer',
                'selection_start' => 'nullable|integer',
                'selection_end' => 'nullable|integer',
                '_broadcast_only' => 'nullable|string',
            ]);
            
            // Check if we're in broadcast-only mode (collaborative editing)
            $broadcastOnly = $request->has('_broadcast_only');
            
            // Handle title update
            if ($request->has('title')) {
                // Only save the note if we're not in broadcast-only mode
                if (!$broadcastOnly) {
                    $note->title = $request->title;
                    $note->save();
                }
                
                // Broadcast the change to others - ensure the socketId is excluded
                event(new \App\Events\NoteTitleUpdated($note, Auth::user(), $request->title));
                
                // Log for debugging
                Log::debug('Broadcasting title update', [
                    'note_id' => $note->id,
                    'user_id' => Auth::id(),
                    'socket_id' => $request->header('X-Socket-ID'),
                    'broadcast_only' => $broadcastOnly
                ]);
            }
            
            // Handle content update
            if ($request->has('content')) {
                // Only save the note if we're not in broadcast-only mode
                if (!$broadcastOnly) {
                    $note->content = $request->content;
                    $note->save();
                }
                
                // Broadcast the change to others - ensure the socketId is excluded
                event(new \App\Events\NoteContentUpdated($note, Auth::user(), $request->content));
                
                // Log for debugging
                Log::debug('Broadcasting content update', [
                    'note_id' => $note->id,
                    'user_id' => Auth::id(),
                    'socket_id' => $request->header('X-Socket-ID'),
                    'broadcast_only' => $broadcastOnly,
                    'content_length' => strlen($request->content)
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => $broadcastOnly ? 'Real-time update broadcast successfully' : 'Real-time update processed successfully',
                'broadcast_only' => $broadcastOnly,
                'socket_id' => $request->header('X-Socket-ID')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in real-time update: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process real-time update: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle user leaving edit session
     */
    public function leaveEditSession(Request $request, Note $note)
    {
        // Ensure the note belongs to the authenticated user or is shared with edit permission
        $isOwner = $note->user_id === Auth::id();
        $isSharedWithEditPermission = $note->isSharedWithUser(Auth::id()) && 
                                    $note->getPermissionForUser(Auth::id()) === 'edit';
        
        if (!$isOwner && !$isSharedWithEditPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        try {
            // Broadcast that the user has left the edit session
            event(new \App\Events\UserLeftEditSession($note, Auth::user()));
            
            return response()->json([
                'success' => true,
                'message' => 'Successfully left edit session',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in leave edit session: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to leave edit session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle user heartbeat to indicate they are still in the edit session
     */
    public function heartbeat(Request $request, Note $note)
    {
        // Ensure the note belongs to the authenticated user or is shared with edit permission
        $isOwner = $note->user_id === Auth::id();
        $isSharedWithEditPermission = $note->isSharedWithUser(Auth::id()) && 
                                    $note->getPermissionForUser(Auth::id()) === 'edit';
        
        if (!$isOwner && !$isSharedWithEditPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        try {
            // Update the last_heartbeat timestamp in cache
            $key = 'note_edit_heartbeat_' . $note->id . '_' . Auth::id();
            $expiresAt = now()->addMinutes(1); // Expires after 1 minute of inactivity
            
            cache()->put($key, now()->timestamp, $expiresAt);
            
            return response()->json([
                'success' => true,
                'message' => 'Heartbeat received',
                'timestamp' => now()->timestamp
            ]);
        } catch (\Exception $e) {
            Log::error('Error in heartbeat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process heartbeat: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a specific note in JSON format for offline use
     */
    public function showJson(Note $note)
    {
        // Ensure the note belongs to the authenticated user or is shared with them
        $isOwner = $note->user_id === Auth::id();
        $isSharedWith = $note->isSharedWithUser(Auth::id());
        
        if (!$isOwner && !$isSharedWith) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }
        
        // Return the note in JSON format
        return response()->json($note);
    }
}
