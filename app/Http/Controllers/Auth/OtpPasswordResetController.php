<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

class OtpPasswordResetController extends Controller
{
    /**
     * Show the OTP verification form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function showVerifyForm(Request $request)
    {
        if (!$request->has('email')) {
            return redirect()->route('password.request');
        }

        return view('auth.passwords.verify-otp', ['email' => $request->email]);
    }

    /**
     * Verify the OTP and allow password reset.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $isValid = PasswordResetOtp::validateOtp($request->email, $request->otp);

        if (!$isValid) {
            return back()->withErrors(['otp' => __('The OTP is invalid or has expired.')]);
        }

        return redirect()->route('password.otp.reset', ['email' => $request->email]);
    }

    /**
     * Show the form to reset password using OTP.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function showResetForm(Request $request)
    {
        if (!$request->has('email')) {
            return redirect()->route('password.request');
        }

        return view('auth.passwords.reset-otp', ['email' => $request->email]);
    }

    /**
     * Reset the user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors(['email' => __('We can\'t find a user with that email address.')]);
        }

        // Reset the password
        $user->password = Hash::make($request->password);
        $user->save();

        // Log the user out
        auth()->logout();

        return redirect()->route('login')
            ->with('status', __('Your password has been reset. Please log in with your new password.'));
    }
}
