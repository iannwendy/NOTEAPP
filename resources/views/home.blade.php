@php
use Illuminate\Support\Str;
@endphp

@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('My Notes') }}</span>
                    <div>
                        <div class="btn-group me-2" role="group" aria-label="View options">
                            <button type="button" class="btn btn-outline-secondary" id="grid-view-btn">
                                <i class="fas fa-th"></i> Grid
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="list-view-btn">
                                <i class="fas fa-list"></i> List
                            </button>
                        </div>
                        <a href="{{ route('notes.create') }}" class="btn btn-primary btn-sm">Create New Note</a>
                    </div>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (count($notes) > 0)
                        <div id="grid-view" class="row">
                            @foreach ($notes as $note)
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100 note-card" style="background-color: {{ $note->color ?? '#ffffff' }}; color: {{ in_array($note->color, ['#212529', '#343a40', '#495057', '#000000', '#111111', '#222222', '#333333']) ? '#ffffff' : '#000000' }};">
                                        <div class="card-body">
                                            <h5 class="card-title">{{ $note->title }}</h5>
                                            <p class="card-text">{{ Str::limit($note->content, 100) }}</p>
                                            <div class="mt-auto">
                                                <a href="{{ route('notes.show', $note) }}" class="btn btn-info btn-sm">View</a>
                                                <a href="{{ route('notes.edit', $note) }}" class="btn btn-warning btn-sm">Edit</a>
                                                <form action="{{ route('notes.destroy', $note) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this note?')">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div id="list-view" class="d-none">
                            <div class="list-group">
                                @foreach ($notes as $note)
                                    <div class="list-group-item list-group-item-action note-card d-flex justify-content-between align-items-center" 
                                         style="background-color: {{ $note->color ?? '#ffffff' }} !important; color: {{ in_array($note->color, ['#212529', '#343a40', '#495057', '#000000', '#111111', '#222222', '#333333']) ? '#ffffff' : '#000000' }} !important; border-color: rgba(0,0,0,.125);">
                                        <div>
                                            <h5 class="mb-1">{{ $note->title }}</h5>
                                            <p class="mb-1">{{ Str::limit($note->content, 150) }}</p>
                                        </div>
                                        <div>
                                            <a href="{{ route('notes.show', $note) }}" class="btn btn-info btn-sm">View</a>
                                            <a href="{{ route('notes.edit', $note) }}" class="btn btn-warning btn-sm">Edit</a>
                                            <form action="{{ route('notes.destroy', $note) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this note?')">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p>You don't have any notes yet. <a href="{{ route('notes.create') }}">Create your first note!</a></p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Always make sure these elements exist, even after page reload
        const gridViewBtn = document.getElementById('grid-view-btn');
        const listViewBtn = document.getElementById('list-view-btn');
        const gridView = document.getElementById('grid-view');
        const listView = document.getElementById('list-view');
        
        // Make sure all elements exist before continuing
        if (!gridViewBtn || !listViewBtn || !gridView || !listView) {
            console.error('Missing elements for view switching');
            return;
        }
        
        // Toggle between grid and list view
        gridViewBtn.addEventListener('click', function() {
            console.log('Grid view clicked');
            gridView.classList.remove('d-none');
            listView.classList.add('d-none');
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
            localStorage.setItem('notesViewPreference', 'grid');
        });
        
        listViewBtn.addEventListener('click', function() {
            console.log('List view clicked');
            gridView.classList.add('d-none');
            listView.classList.remove('d-none');
            gridViewBtn.classList.remove('active');
            listViewBtn.classList.add('active');
            localStorage.setItem('notesViewPreference', 'list');
        });
        
        // Check if user has a saved preference
        const savedPreference = localStorage.getItem('notesViewPreference');
        console.log('Saved preference:', savedPreference);
        
        // Apply the saved preference or default to grid view
        if (savedPreference === 'list') {
            listViewBtn.click();
        } else {
            // Default to grid view if no preference or preference is grid
            gridViewBtn.click();
        }
        
        // Auto-detect dark note colors and apply appropriate class for contrast
        const noteCards = document.querySelectorAll('.note-card');
        noteCards.forEach(card => {
            const backgroundColor = getComputedStyle(card).backgroundColor;
            const rgb = backgroundColor.match(/\d+/g);
            
            if (rgb && rgb.length >= 3) {
                const r = parseInt(rgb[0]);
                const g = parseInt(rgb[1]);
                const b = parseInt(rgb[2]);
                
                // Calculate brightness (higher values are lighter colors)
                const brightness = (r * 299 + g * 587 + b * 114) / 1000;
                
                // If the note has a dark background color
                if (brightness < 128) {
                    card.classList.add('dark-note');
                }
            }
        });
    });
</script>
@endpush
@endsection
