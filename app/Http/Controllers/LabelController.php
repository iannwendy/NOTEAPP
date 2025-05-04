<?php

namespace App\Http\Controllers;

use App\Models\Label;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        
        $note = Auth::user()->notes()->findOrFail($request->note_id);
        $label = Auth::user()->labels()->findOrFail($request->label_id);
        
        // Check if the label is already attached to the note
        if (!$note->labels()->where('label_id', $label->id)->exists()) {
            $note->labels()->attach($label);
        }
        
        return response()->json([
            'success' => true,
            'message' => "Label \"{$label->name}\" added to note",
            'label' => $label
        ]);
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
        
        $note = Auth::user()->notes()->findOrFail($request->note_id);
        $label = Auth::user()->labels()->findOrFail($request->label_id);
        
        $note->labels()->detach($label);
        
        return response()->json([
            'success' => true,
            'message' => "Label \"{$label->name}\" removed from note"
        ]);
    }
    
    /**
     * Get all labels for the authenticated user.
     */
    public function getAllLabels()
    {
        $userId = Auth::id();
        
        // Explicitly query by user_id to ensure proper user scope
        $labels = Label::where('user_id', $userId)
                       ->orderBy('name')
                       ->get();
        
        \Log::debug('Labels loaded for user', [
            'user_id' => $userId,
            'count' => $labels->count(),
            'labels' => $labels->pluck('name', 'id')->toArray()
        ]);
        
        return response()->json([
            'success' => true,
            'labels' => $labels
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache')
          ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }
}
