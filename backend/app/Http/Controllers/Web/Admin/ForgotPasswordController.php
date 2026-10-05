<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminPasswordOtpMail;
use App\Models\PasswordChangeOtp;
use App\Models\User;
use App\Support\BlindIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function requestCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);
        $request->session()->forget('admin_recovery_id');
        $admin = User::query()->where('email_index', BlindIndex::make($data['email']))
            ->where('role', User::ROLE_ADMIN)->where('is_active', true)->first();
        if ($admin !== null) {
            $otp = (string) random_int(100000, 999999);
            $challenge = PasswordChangeOtp::query()->create([
                'user_id' => $admin->id,
                'otp_hash' => Hash::make($otp),
                'new_password_hash' => Hash::make($data['password']),
                'expires_at' => now()->addMinutes(10),
            ]);
            Mail::to($admin->email)->send(new AdminPasswordOtpMail(
                $otp, $challenge->expires_at->format('j M Y, H:i T'),
            ));
            $request->session()->put('admin_recovery_id', $challenge->id);
        }
        $request->session()->put('admin_recovery_requested', true);

        return redirect()->route('admin.recovery.show')->with('status',
            'If this email belongs to an active administrator, a code has been sent. It expires in 10 minutes.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['otp' => ['required', 'digits:6']]);
        $success = DB::transaction(function () use ($request, $data): bool {
            $challenge = PasswordChangeOtp::query()->whereKey($request->session()->get('admin_recovery_id'))
                ->lockForUpdate()->first();
            if ($challenge === null || $challenge->consumed_at !== null
                || $challenge->expires_at->lte(now()) || $challenge->attempts >= 5) {
                return false;
            }
            $challenge->increment('attempts');
            if (! Hash::check($data['otp'], $challenge->otp_hash)) {
                return false;
            }
            $admin = User::query()->whereKey($challenge->user_id)->lockForUpdate()->first();
            if ($admin === null || $admin->role !== User::ROLE_ADMIN || ! $admin->is_active) {
                return false;
            }
            $admin->forceFill(['password' => $challenge->new_password_hash,
                'remember_token' => Str::random(60)])->save();
            PasswordChangeOtp::query()->where('user_id', $admin->id)->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            return true;
        });
        if (! $success) {
            return back()->withErrors(['otp' => 'The code is incorrect or expired. Request a new code if needed.']);
        }
        $request->session()->forget(['admin_recovery_id', 'admin_recovery_requested']);
        $request->session()->regenerate();

        return redirect()->route('admin.login')->with('status', 'Your password was reset. Sign in with your new password.');
    }
}
