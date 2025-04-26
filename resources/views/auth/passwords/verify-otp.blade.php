@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Verify OTP Code') }}</div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <p class="mb-3">{{ __('Please enter the OTP code sent to your email address.') }}</p>

                    <form method="POST" action="{{ route('password.otp.verify.submit') }}">
                        @csrf

                        <input type="hidden" name="email" value="{{ $email }}">

                        <div class="mb-3 row">
                            <label for="otp" class="col-md-4 col-form-label text-md-end">{{ __('OTP Code') }}</label>

                            <div class="col-md-6">
                                <input id="otp" type="text" class="form-control @error('otp') is-invalid @enderror" name="otp" required autofocus maxlength="6" pattern="\d{6}">

                                @error('otp')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-0 row">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Verify OTP') }}
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="mt-3 text-center">
                        <p>{{ __('Didn\'t receive the OTP?') }} <a href="{{ route('password.email') }}?email={{ $email }}">{{ __('Resend Reset Instructions') }}</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 