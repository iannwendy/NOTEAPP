@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('Share Note') }}</span>
                    <a href="{{ route('notes.shares.show', $note) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to Shares
                    </a>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    <h5 class="mb-4">Share "{{ $note->title }}" with others</h5>

                    <form method="POST" action="{{ route('notes.shares.store', $note) }}" class="mb-4">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Recipient Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                            <div class="form-text">Enter the email address of a registered user you want to share this note with.</div>
                            @error('email')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Permission</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="permission" id="permission_read" value="read" checked>
                                <label class="form-check-label" for="permission_read">
                                    <i class="fas fa-eye me-1"></i> Read-only
                                    <div class="form-text">User can only view the note but cannot make changes.</div>
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="radio" name="permission" id="permission_edit" value="edit">
                                <label class="form-check-label" for="permission_edit">
                                    <i class="fas fa-edit me-1"></i> Edit access
                                    <div class="form-text">User can view and edit the note's content.</div>
                                </label>
                            </div>
                            @error('permission')
                                <span class="invalid-feedback d-block" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="alert alert-info" role="alert">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-1"></i> About Sharing Notes</h6>
                            <ul class="mb-0">
                                <li>The person you share with must have a registered account.</li>
                                <li>You can change or revoke access at any time.</li>
                                <li>Shared notes will appear in the recipient's "Shared with Me" section.</li>
                                <li>Password-protected notes will still be accessible to people you share with.</li>
                            </ul>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-share-alt me-1"></i> Share Note
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 