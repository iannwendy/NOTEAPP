@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Add Images to') }}: {{ $note->title }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('notes.attachments.store', $note) }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="file" class="form-label">{{ __('Choose Images') }}</label>
                            <input id="file" type="file" class="form-control @error('file') is-invalid @enderror" name="file[]" accept="image/*" multiple required>
                            @error('file')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                            <div class="form-text">
                                Maximum file size: 10MB per image. Accepted formats: JPG, PNG, GIF, etc.
                                <br>You can select multiple images at once.
                            </div>
                        </div>

                        <div class="mb-0">
                            <button type="submit" class="btn btn-primary">
                                {{ __('Upload Images') }}
                            </button>
                            <a href="{{ route('notes.show', $note) }}" class="btn btn-secondary">
                                {{ __('Cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 