@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('Note Sharing') }}</span>
                    <div>
                        <a href="{{ route('notes.shares.create', $note) }}" class="btn btn-primary btn-sm me-2">
                            <i class="fas fa-user-plus me-1"></i> Share with More People
                        </a>
                        <a href="{{ route('notes.show', $note) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Back to Note
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    <h5 class="mb-3">Sharing Settings for "{{ $note->title }}"</h5>
                    
                    <p class="text-muted mb-4">
                        <i class="fas fa-info-circle me-1"></i> Manage who has access to this note and what they can do with it.
                    </p>

                    @if($shares->isEmpty())
                        <div class="alert alert-info" role="alert">
                            <h6><i class="fas fa-info-circle me-1"></i> Not Shared Yet</h6>
                            <p class="mb-0">This note is not shared with anyone yet. Use the "Share with More People" button to start sharing.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Permission</th>
                                        <th>Shared Since</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($shares as $share)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="{{ $share->user->avatar_url }}" alt="Avatar" class="rounded-circle me-2" width="32" height="32">
                                                    <div>
                                                        <div>{{ $share->user->name }}</div>
                                                        <small class="text-muted">{{ $share->user->email }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge {{ $share->permission === 'read' ? 'bg-info' : 'bg-warning' }}">
                                                    <i class="fas {{ $share->permission === 'read' ? 'fa-eye' : 'fa-edit' }} me-1"></i>
                                                    {{ $share->permission === 'read' ? 'Read-only' : 'Can edit' }}
                                                </span>
                                            </td>
                                            <td>{{ $share->created_at->format('M d, Y') }}</td>
                                            <td>
                                                <div class="d-flex">
                                                    <!-- Change permission dropdown -->
                                                    <div class="dropdown me-2">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="permission-{{ $share->id }}" data-bs-toggle="dropdown" aria-expanded="false">
                                                            Change
                                                        </button>
                                                        <ul class="dropdown-menu" aria-labelledby="permission-{{ $share->id }}">
                                                            <li>
                                                                <form method="POST" action="{{ route('notes.shares.update', ['note' => $note, 'share' => $share]) }}">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <input type="hidden" name="permission" value="read">
                                                                    <button type="submit" class="dropdown-item {{ $share->permission === 'read' ? 'active' : '' }}">
                                                                        <i class="fas fa-eye me-1"></i> Read-only
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <li>
                                                                <form method="POST" action="{{ route('notes.shares.update', ['note' => $note, 'share' => $share]) }}">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <input type="hidden" name="permission" value="edit">
                                                                    <button type="submit" class="dropdown-item {{ $share->permission === 'edit' ? 'active' : '' }}">
                                                                        <i class="fas fa-edit me-1"></i> Can edit
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>

                                                    <!-- Remove access button -->
                                                    <form method="POST" action="{{ route('notes.shares.destroy', ['note' => $note, 'share' => $share]) }}" onsubmit="return confirm('Are you sure you want to remove access for {{ $share->user->email }}?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-user-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 