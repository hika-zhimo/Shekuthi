<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\User;
use App\Models\VerificationVolunteer;
use App\Support\BlindIndex;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_demo_accounts_have_role_profiles_and_registration_consents(): void
    {
        $this->seed(DemoAccountsSeeder::class);

        $this->assertDatabaseCount('users', 7);
        $this->assertDatabaseCount('consents', 6);
        foreach (User::query()->get() as $user) {
            $this->assertTrue(Hash::check(DemoAccountsSeeder::PASSWORD, $user->password));
            if ($user->role === User::ROLE_ADMIN) {
                continue;
            }
            $this->assertDatabaseHas('consents', [
                'subject_type' => 'user',
                'subject_id' => $user->id,
                'consent_key' => Consent::KEY_REGISTRATION,
                'text_version' => config('legal.consent_version'),
            ]);
        }
        $this->assertDatabaseCount('vendors', 1);
        $this->assertDatabaseCount('driver_availability', 2);
        $this->assertDatabaseCount('worker_profiles', 1);
        $this->assertDatabaseCount('verification_volunteers', 2);
        $this->assertSame(VerificationVolunteer::STATUS_APPROVED,
            $this->demoUser('volunteer@demo.test')->verificationVolunteer->verification_status);
        $this->assertSame(VerificationVolunteer::STATUS_PENDING,
            $this->demoUser('pending.volunteer@demo.test')->verificationVolunteer->verification_status);
    }

    public function test_repeat_seeding_resets_passwords_and_repairs_missing_role_rows_without_duplicates(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        $vendor = $this->demoUser('vendor@demo.test');
        $vendor->forceFill(['password' => 'changed1234', 'is_active' => false])->save();
        $vendor->vendor->delete();
        $this->seed(DemoAccountsSeeder::class);

        $this->assertDatabaseCount('users', 7);
        $this->assertDatabaseCount('consents', 6);
        $this->assertDatabaseCount('vendors', 1);
        $this->assertDatabaseCount('driver_availability', 2);
        $this->assertDatabaseCount('worker_profiles', 1);
        $this->assertDatabaseCount('verification_volunteers', 2);
        $this->assertTrue($vendor->fresh()->is_active);
        $this->assertTrue(Hash::check(DemoAccountsSeeder::PASSWORD, $vendor->fresh()->password));
        $this->assertNotNull($vendor->fresh()->vendor);
    }

    public function test_default_seeding_does_not_create_demo_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    private function demoUser(string $email): User
    {
        return User::query()->where('email_index', BlindIndex::make($email))->firstOrFail();
    }
}
