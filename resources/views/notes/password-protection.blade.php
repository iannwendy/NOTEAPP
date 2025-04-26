@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('Manage Password Protection') }}</span>
                    <a href="{{ route('notes.show', $note) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Back to Note
                    </a>
                </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    <h5 class="card-title mb-4">Password Protection for "{{ $note->title }}"</h5>

                    <form method="POST" action="{{ route('notes.update-password-protection', $note) }}">
                        @csrf
                        <input type="hidden" name="is_currently_protected" value="{{ $note->is_password_protected ? '1' : '0' }}">
                        <input type="hidden" name="enable_protection" value="0">

                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="enable_protection" name="enable_protection" value="1" {{ (old('enable_protection') == '1' || $note->is_password_protected) && old('enable_protection') !== '0' ? 'checked' : '' }}>
                                <label class="form-check-label" for="enable_protection">
                                    Enable password protection for this note
                                </label>
                            </div>
                            <div class="form-text text-muted">
                                When enabled, users will need to enter a password before viewing, editing, or deleting this note.
                            </div>
                        </div>

                        <!-- Current password field (always shown if note is currently protected) -->
                        <div id="current_password_field" class="mb-3 {{ $note->is_password_protected || $errors->has('current_password') ? '' : 'd-none' }}">
                            <label for="current_password" class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" {{ $note->is_password_protected ? 'required' : '' }} value="{{ old('current_password') }}">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            @error('current_password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                            <div class="form-text">Enter current password to change settings or disable protection.</div>
                        </div>

                        <!-- New password field (only shown when enabling protection) -->
                        <div id="password-fields" class="mb-3 {{ (old('enable_protection') == '1' && $errors->any()) || $note->is_password_protected ? '' : 'd-none' }}">
                            <div class="mb-3">
                                <label for="password" class="form-label">{{ $note->is_password_protected ? 'New Password' : 'Password' }}</label>
                                <div class="input-group">
                                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" {{ $note->is_password_protected ? '' : 'required' }} value="{{ old('password') }}">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <div class="form-text">{{ $note->is_password_protected ? 'Leave blank to keep current password.' : 'Minimum 6 characters.' }}</div>
                            </div>

                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" id="password_confirmation" name="password_confirmation" {{ $note->is_password_protected ? '' : 'required' }} value="{{ old('password_confirmation') }}">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password_confirmation">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                @error('password_confirmation')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <div class="form-text">Re-enter the password to confirm.</div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4">
                            <h6><i class="fas fa-info-circle me-2"></i>About Password Protection</h6>
                            <ul class="mb-0">
                                <li>Each note can have its own unique password</li>
                                <li>Password protection prevents unauthorized access to your note</li>
                                <li>You'll need to enter the password each time you access this note</li>
                                <li>If you forget the password, you won't be able to access the note's content</li>
                                <li>You must enter the password twice when setting it to prevent typing errors</li>
                                <li>To disable protection, you must first verify the current password as a security measure</li>
                            </ul>
                        </div>

                        <div id="disable-warning" class="alert alert-warning mt-4 d-none">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Warning: Disabling Password Protection</h6>
                            <p>You are about to remove password protection from this note. Once disabled, anyone with access to your account will be able to view this note without entering a password.</p>
                            <p class="mb-0"><strong>This action requires your current password for confirmation.</strong></p>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle new password fields based on checkbox
        const enableProtection = document.getElementById('enable_protection');
        const passwordFields = document.getElementById('password-fields');
        const currentPasswordField = document.getElementById('current_password_field');
        const currentPasswordInput = document.getElementById('current_password');
        const disableWarning = document.getElementById('disable-warning');
        const isCurrentlyProtected = {{ $note->is_password_protected ? 'true' : 'false' }};
        const hasErrors = {{ $errors->any() ? 'true' : 'false' }};
        
        // Initialize form state correctly if there are validation errors
        if (hasErrors) {
            if (enableProtection.checked) {
                passwordFields.classList.remove('d-none');
                disableWarning.classList.add('d-none');
            } else if (isCurrentlyProtected) {
                passwordFields.classList.add('d-none');
                currentPasswordField.classList.remove('d-none');
                disableWarning.classList.remove('d-none');
            }
        }
        
        enableProtection.addEventListener('change', function() {
            if (this.checked) {
                // Enabling protection - show password fields
                passwordFields.classList.remove('d-none');
                // Only show current password if already protected
                if (isCurrentlyProtected) {
                    currentPasswordField.classList.remove('d-none');
                    currentPasswordInput.setAttribute('required', '');
                }
                // Hide warning
                disableWarning.classList.add('d-none');
            } else {
                // Disabling protection
                passwordFields.classList.add('d-none');
                
                // Always show current password field if turning off protection
                if (isCurrentlyProtected) {
                    currentPasswordField.classList.remove('d-none');
                    currentPasswordInput.setAttribute('required', '');
                    // Show warning
                    disableWarning.classList.remove('d-none');
                }
            }
        });
        
        // Toggle password visibility
        const toggleBtns = document.querySelectorAll('.toggle-password');
        
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    });
</script>
@endpush
@endsection 