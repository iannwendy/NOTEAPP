<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Note $note)
    {
        // Ensure the note belongs to the authenticated user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        return view('attachments.create', compact('note'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Note $note)
    {
        // Ensure the note belongs to the authenticated user
        if ($note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'file' => 'required|array',
            'file.*' => 'file|image|max:10240', // 10MB max, only images accepted
        ]);

        $successCount = 0;
        $errors = [];

        foreach ($request->file('file') as $file) {
            try {
                $originalFilename = $file->getClientOriginalName();
                $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
                $fileType = $file->getMimeType();
                $fileSize = $file->getSize();
                
                // Store the file in the storage/app/attachments directory
                $path = $file->storeAs('attachments', $filename);
                
                // Create attachment record
                $attachment = $note->attachments()->create([
                    'filename' => $filename,
                    'original_filename' => $originalFilename,
                    'file_type' => $fileType,
                    'file_path' => $path,
                    'file_size' => $fileSize
                ]);
                
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = $originalFilename . ': ' . $e->getMessage();
            }
        }
        
        if ($successCount > 0 && count($errors) == 0) {
            return redirect()->route('notes.show', $note)
                ->with('success', $successCount . ' ' . ($successCount == 1 ? 'image' : 'images') . ' attached successfully.');
        } elseif ($successCount > 0 && count($errors) > 0) {
            return redirect()->route('notes.show', $note)
                ->with('warning', $successCount . ' ' . ($successCount == 1 ? 'image' : 'images') . ' attached successfully, but ' . count($errors) . ' failed.');
        } else {
            return redirect()->back()
                ->withErrors(['file' => 'Failed to upload images: ' . implode(', ', $errors)]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Download the specified attachment.
     */
    public function download(Attachment $attachment)
    {
        // Ensure the attachment's note belongs to the authenticated user
        if ($attachment->note->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        return Storage::download(
            $attachment->file_path,
            $attachment->original_filename
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Note $note, Attachment $attachment)
    {
        // Ensure the attachment belongs to the authenticated user's note
        if ($note->user_id !== Auth::id() || $attachment->note_id !== $note->id) {
            abort(403, 'Unauthorized action.');
        }

        // Delete the file from storage
        Storage::delete($attachment->file_path);
        
        // Delete the attachment record
        $attachment->delete();

        return redirect()->route('notes.show', $note)
            ->with('success', 'Attachment deleted successfully.');
    }
}
