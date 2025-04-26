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
                    <span>{{ __('Shared with Me') }}</span>
                    <a href="{{ route('notes.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to My Notes
                    </a>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    <h5 class="mb-4">Notes shared with you by others</h5>

                    @if($sharedNotes->isEmpty())
                        <div class="alert alert-info">
                            <h5 class="alert-heading"><i class="fas fa-info-circle me-1"></i> No Shared Notes</h5>
                            <p class="mb-0">You don't have any notes shared with you yet. When someone shares a note with you, it will appear here.</p>
                        </div>
                    @else
                        <div class="row">
                            @foreach($sharedNotes as $note)
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm" style="background-color: {{ $note->color ?? '#ffffff' }}; color: {{ in_array($note->color, ['#212529', '#343a40', '#495057', '#000000', '#111111', '#222222', '#333333']) ? '#ffffff' : '#000000' }};">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="card-title mb-0">{{ $note->title }}</h5>
                                                <span class="badge {{ $note->pivot->permission === 'read' ? 'bg-info' : 'bg-warning' }}">
                                                    <i class="fas {{ $note->pivot->permission === 'read' ? 'fa-eye' : 'fa-edit' }} me-1"></i>
                                                    {{ $note->pivot->permission === 'read' ? 'Read-only' : 'Can edit' }}
                                                </span>
                                            </div>
                                            
                                            <p class="card-text">{{ Str::limit($note->content, 150) }}</p>
                                            
                                            <div class="d-flex align-items-center mt-3">
                                                <img src="{{ $note->user->avatar_url }}" alt="Owner Avatar" class="rounded-circle me-2" width="24" height="24">
                                                <small class="text-muted">Shared by: {{ $note->user->name }}</small>
                                            </div>
                                            
                                            @if($note->labels && $note->labels->count() > 0)
                                                <div class="mt-3">
                                                    @foreach($note->labels as $label)
                                                        <span class="badge mb-1 me-1" style="background-color: {{ $label->color }};">
                                                            {{ $label->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            
                                            <div class="mt-3">
                                                <a href="{{ route('notes.show', $note) }}" class="btn btn-primary btn-sm">View</a>
                                                @if($note->pivot->permission === 'edit')
                                                    <a href="{{ route('notes.edit', $note) }}" class="btn btn-warning btn-sm">Edit</a>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <small class="text-muted">Shared: {{ $note->pivot->created_at->format('M d, Y') }}</small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 