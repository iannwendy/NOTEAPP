@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Edit Preferences') }}</div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('preferences.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="font_size" class="form-label">{{ __('Font Size') }}</label>
                            <select id="font_size" class="form-select @error('font_size') is-invalid @enderror" name="font_size" required>
                                <option value="small" {{ (old('font_size', $preferences['font_size'] ?? '') == 'small') ? 'selected' : '' }}>Small</option>
                                <option value="medium" {{ (old('font_size', $preferences['font_size'] ?? '') == 'medium') ? 'selected' : '' }}>Medium</option>
                                <option value="large" {{ (old('font_size', $preferences['font_size'] ?? '') == 'large') ? 'selected' : '' }}>Large</option>
                            </select>
                            @error('font_size')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="theme" class="form-label">{{ __('Theme') }}</label>
                            <select id="theme" class="form-select @error('theme') is-invalid @enderror" name="theme" required>
                                <option value="light" {{ (old('theme', $preferences['theme'] ?? '') == 'light') ? 'selected' : '' }}>Light</option>
                                <option value="dark" {{ (old('theme', $preferences['theme'] ?? '') == 'dark') ? 'selected' : '' }}>Dark</option>
                            </select>
                            @error('theme')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="note_color" class="form-label">{{ __('Default Note Color') }}</label>
                            <input type="color" class="form-control form-control-color @error('note_color') is-invalid @enderror" id="note_color" name="note_color" value="{{ old('note_color', $preferences['note_color'] ?? '#ffffff') }}" title="Choose your default note color">
                            @error('note_color')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-0">
                            <button type="submit" class="btn btn-primary">
                                {{ __('Save Preferences') }}
                            </button>
                            <a href="{{ route('home') }}" class="btn btn-secondary">
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