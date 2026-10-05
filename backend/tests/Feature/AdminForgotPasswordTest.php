<?php

namespace Tests\Feature;

use App\Mail\AdminPasswordOtpMail;
use App\Models\PasswordChangeOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_requires_valid_single_use_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->get(route('admin.login'))->assertOk()->assertSee('Forgot password?');
        $this->get(route('admin.recovery.show'))->assertOk();
        $this->post(route('admin.recovery.request'), [
            'email' => $user->email, 'password' => 'ReplacementPassword123!',
            'password_confirmation' => 'ReplacementPassword123!',
        ])->assertRedirect(route('admin.recovery.show'));
        $otp = null;
        Mail::assertSent(AdminPasswordOtpMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });
        $this->post(route('admin.recovery.confirm'), ['otp' => '000000'])->assertSessionHasErrors('otp');
        $this->assertSame(1, PasswordChangeOtp::firstOrFail()->attempts);
        $this->assertFalse(Hash::check('ReplacementPassword123!', $user->fresh()->password));
        $this->post(route('admin.recovery.confirm'), ['otp' => $otp])->assertRedirect(route('admin.login'));
        $this->assertTrue(Hash::check('ReplacementPassword123!', $user->fresh()->password));
        $this->post(route('admin.recovery.confirm'), ['otp' => $otp])->assertSessionHasErrors('otp');
    }

    public function test_non_admin_receives_generic_response_without_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'driver']);
        $this->post(route('admin.recovery.request'), [
            'email' => $user->email, 'password' => 'ReplacementPassword123!',
            'password_confirmation' => 'ReplacementPassword123!',
        ])->assertRedirect(route('admin.recovery.show'))->assertSessionHas('status');
        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_change_otps', 0);
    }

    public function test_expired_exhausted_and_unbound_codes_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $challenge = PasswordChangeOtp::create([
            'user_id' => $user->id, 'otp_hash' => Hash::make('123456'),
            'new_password_hash' => Hash::make('ReplacementPassword123!'),
            'expires_at' => now()->subMinute(),
        ]);
        $this->withSession(['admin_recovery_id' => $challenge->id])
            ->post(route('admin.recovery.confirm'), ['otp' => '123456'])->assertSessionHasErrors('otp');
        $challenge->update(['expires_at' => now()->addMinutes(10), 'attempts' => 5]);
        $this->post(route('admin.recovery.confirm'), ['otp' => '123456'])->assertSessionHasErrors('otp');
        $challenge->update(['attempts' => 0]);
        $this->app['session']->forget('admin_recovery_id');
        $this->post(route('admin.recovery.confirm'), ['otp' => '123456'])->assertSessionHasErrors('otp');
        $this->assertFalse(Hash::check('ReplacementPassword123!', $user->fresh()->password));
    }

    public function test_disabled_admin_and_unknown_email_receive_same_response(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        foreach ([$user->email, 'unknown@shekuthi.in'] as $email) {
            $this->post(route('admin.recovery.request'), [
                'email' => $email, 'password' => 'ReplacementPassword123!',
                'password_confirmation' => 'ReplacementPassword123!',
            ])->assertSessionHas('status', 'If this email belongs to an active administrator, a code has been sent. It expires in 10 minutes.');
        }
        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_change_otps', 0);
    }
}
