@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('Change Avatar') }}</span>
                    <a href="{{ route('user.profile') }}" class="btn btn-secondary btn-sm">Back to Profile</a>
                </div>

                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4 text-center mb-4">
                            <div class="mb-3">
                                <strong>Current Avatar</strong>
                            </div>
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}'s Avatar" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;" onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=random&color=fff'; console.error('Failed to load avatar image');">
                        </div>
                        <div class="col-md-8">
                            <form method="POST" action="{{ route('user.update-avatar') }}" enctype="multipart/form-data">
                                @csrf
                                @method('PATCH')

                                <div class="mb-3">
                                    <label for="avatar" class="form-label">{{ __('Choose New Avatar') }}</label>
                                    <input id="avatar" type="file" class="form-control @error('avatar') is-invalid @enderror" name="avatar" required accept="image/*">
                                    <div class="form-text text-muted mt-1">
                                        Accepted image formats: JPG, PNG, GIF. Maximum size: 1MB.
                                    </div>

                                    @error('avatar')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        {{ __('Update Avatar') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h5>Avatar Tips</h5>
                        <ul class="mb-0">
                            <li>Use a square image for best results</li>
                            <li>Minimum recommended size: 200x200 pixels</li>
                            <li>For best display quality, use a clear, well-lit photo</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 