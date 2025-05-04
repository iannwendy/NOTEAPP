<?php

namespace App\Http\Controllers;

use App\Models\Label;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LabelController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $labels = Auth::user()->labels()->orderBy('name')->get();
        return view('labels.index', compact('labels'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('labels.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 
                'string', 
                'max:50',
                Rule::unique('labels')->where(function ($query) {
                    return $query->where('user_id', Auth::id());
                })
            ],
            'color' => 'nullable|string|max:20',
        ]);
        
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            return back()->withErrors($validator)->withInput();
        }
        
        $label = Auth::user()->labels()->create([
            'name' => $request->name,
            'color' => $request->color ?? '#6c757d',
        ]);
        
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'label' => $label,
                'message' => 'Label created successfully'
            ]);
        }
        
        return redirect()->route('labels.index')
            ->with('success', 'Label created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Label $label)
    {
        // Ensure the label belongs to the authenticated user
        if ($label->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        return view('labels.edit', compact('label'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Label $label)
    {
        // Ensure the label belongs to the authenticated user
        if ($label->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 
                'string', 
                'max:50',
                Rule::unique('labels')->where(function ($query) {
                    return $query->where('user_id', Auth::id());
                })->ignore($label->id)
            ],
            'color' => 'nullable|string|max:20',
        ]);
        
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            return back()->withErrors($validator)->withInput();
        }
        
        $label->update([
            'name' => $request->name,
            'color' => $request->color ?? $label->color,
        ]);
        
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'label' => $label,
                'message' => 'Label updated successfully'
            ]);
        }
        
        return redirect()->route('labels.index')
            ->with('success', 'Label updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Label $label)
    {
        // Ensure the label belongs to the authenticated user
        if ($label->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        $label->delete();
        
        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Label deleted successfully'
            ]);
        }
        
        return redirect()->route('labels.index')
            ->with('success', 'Label deleted successfully.');
    }
    
    /**
     * Add a label to a note
     */
    public function addToNote(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'note_id' => 'required|exists:notes,id',
            'label_id' => 'required|exists:labels,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Get the note and ensure it belongs to the authenticated user
            $note = Auth::user()->notes()->findOrFail($request->note_id);
            
            // Get the label and ensure it belongs to the authenticated user
            $label = Auth::user()->labels()->findOrFail($request->label_id);
            
            // Log the action for debugging
            Log::debug('Adding label to note', [
                'user_id' => Auth::id(),
                'note_id' => $note->id,
                'note_user_id' => $note->user_id,
                'label_id' => $label->id,
                'label_user_id' => $label->user_id
            ]);
            
            // Check if the label is already attached to the note
            if (!$note->labels()->where('label_id', $label->id)->exists()) {
                $note->labels()->attach($label);
            }
            
            return response()->json([
                'success' => true,
                'message' => "Label \"{$label->name}\" added to note",
                'label' => $label
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Label or note not found for user during addToNote', [
                'user_id' => Auth::id(),
                'note_id' => $request->note_id,
                'label_id' => $request->label_id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Note or label not found or does not belong to you'
            ], 403);
        } catch (\Exception $e) {
            Log::error('Error adding label to note', [
                'user_id' => Auth::id(),
                'note_id' => $request->note_id,
                'label_id' => $request->label_id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while adding the label'
            ], 500);
        }
    }
    
    /**
     * Remove a label from a note
     */
    public function removeFromNote(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'note_id' => 'required|exists:notes,id',
            'label_id' => 'required|exists:labels,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Get the note and ensure it belongs to the authenticated user
            $note = Auth::user()->notes()->findOrFail($request->note_id);
            
            // Get the label and ensure it belongs to the authenticated user
            $label = Auth::user()->labels()->findOrFail($request->label_id);
            
            // Log the action for debugging
            Log::debug('Removing label from note', [
                'user_id' => Auth::id(),
                'note_id' => $note->id,
                'label_id' => $label->id
            ]);
            
            $note->labels()->detach($label);
            
            return response()->json([
                'success' => true,
                'message' => "Label \"{$label->name}\" removed from note"
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Label or note not found for user during removeFromNote', [
                'user_id' => Auth::id(),
                'note_id' => $request->note_id,
                'label_id' => $request->label_id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Note or label not found or does not belong to you'
            ], 403);
        } catch (\Exception $e) {
            Log::error('Error removing label from note', [
                'user_id' => Auth::id(),
                'note_id' => $request->note_id,
                'label_id' => $request->label_id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while removing the label'
            ], 500);
        }
    }
    
    /**
     * Get all labels for the authenticated user.
     */
    public function getAllLabels()
    {
        // Ensure user is authenticated
        if (!Auth::check()) {
            Log::error('Unauthenticated user attempting to access labels');
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $userId = Auth::id();
        
        // Log the current authenticated user
        Log::debug('Label getAllLabels requested by user', [
            'auth_user_id' => $userId,
            'auth_user_email' => Auth::user()->email
        ]);
        
        // Explicitly query by user_id to ensure proper user scope
        // Use Auth::user()->labels() relationship to guarantee correct user scope
        $labels = Auth::user()->labels()
                              ->orderBy('name')
                              ->get();
        
        // Double-check that all returned labels belong to the current user
        $nonUserLabels = $labels->filter(function ($label) use ($userId) {
            return $label->user_id !== $userId;
        });
        
        if ($nonUserLabels->count() > 0) {
            Log::error('Security issue: Label query returned labels from other users', [
                'auth_user_id' => $userId,
                'non_user_labels' => $nonUserLabels->pluck('id', 'user_id')->toArray()
            ]);
            
            // Filter out non-user labels for safety
            $labels = $labels->filter(function ($label) use ($userId) {
                return $label->user_id === $userId;
            });
        }
        
        // Debug - log all labels in the system to check for data isolation issues
        $allLabels = Label::all();
        Log::debug('All labels in system:', [
            'count' => $allLabels->count(),
            'by_user' => $allLabels->groupBy('user_id')->map(function ($items) {
                return $items->count();
            })->toArray()
        ]);
        
        Log::debug('Labels loaded for user', [
            'user_id' => $userId,
            'count' => $labels->count(),
            'labels' => $labels->pluck('name', 'id')->toArray()
        ]);
        
        // Return success even if no labels found
        return response()->json([
            'success' => true,
            'labels' => $labels,
            'message' => $labels->count() > 0 ? null : 'No labels found. Create your first label!'
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }
}
