@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('User Profile') }}</span>
                    <a href="{{ route('notes.index') }}" class="btn btn-secondary btn-sm">Back to Notes</a>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}'s Avatar" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=random&color=fff'; console.error('Failed to load avatar image');">
                        </div>
                        <div class="col-md-8">
                            <h3>{{ $user->name }}</h3>
                            <p class="text-muted">{{ $user->email }}</p>
                            
                            @if($user->bio)
                                <div class="mt-3">
                                    <h5>About</h5>
                                    <p>{{ $user->bio }}</p>
                                </div>
                            @endif
                            
                            <div class="mt-4">
                                <a href="{{ route('user.edit-avatar') }}" class="btn btn-primary me-2">Change Avatar</a>
                                <a href="{{ route('user.edit-profile') }}" class="btn btn-outline-primary me-2">Edit Profile</a>
                                <a href="{{ route('user.edit-password') }}" class="btn btn-outline-secondary">Change Password</a>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h4>Account Information</h4>
                        <table class="table table-striped">
                            <tbody>
                                <tr>
                                    <th style="width: 30%">Member Since</th>
                                    <td>{{ $user->getFormattedCreatedAt() }}</td>
                                </tr>
                                <tr>
                                    <th>Email Verified</th>
                                    <td>
                                        @if($user->email_verified_at)
                                            <span class="text-success">Verified on {{ $user->getFormattedVerifiedAt() }}</span>
                                        @else
                                            <span class="text-danger">Not verified</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Account Status</th>
                                    <td>
                                        @if($user->is_activated)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-warning">Not Activated</span>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 