<?php

namespace Database\Seeders;

use App\Models\DriverAvailability;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VerificationVolunteer;
use App\Models\WorkerProfile;
use App\Services\RegistrationService;
use App\Support\BlindIndex;
use Illuminate\Database\Seeder;

/**
 * Local demo accounts (M16.1). Run on demand:
 *
 *     php artisan db:seed --class=DemoAccountsSeeder
 *
 * Deliberately NOT registered in DatabaseSeeder, so a production
 * `migrate --seed` never creates these. Idempotent: re-running resets the
 * passwords and recreates any missing role row.
 *
 * All accounts use the password `test1234`. Members sign in at /login (or
 * the API); the admin signs in at /admin/login.
 */
class DemoAccountsSeeder extends Seeder
{
    public const PASSWORD = 'test1234';

    /** @var array<int, array<string, mixed>> */
    private const ACCOUNTS = [
        [
            'role' => User::ROLE_VENDOR,
            'email' => 'vendor@demo.test',
            'name' => 'Demo Vendor',
            'display_name' => 'Demo Farm',
            'vendor_category' => 'agro',
        ],
        [
            'role' => User::ROLE_DRIVER,
            'email' => 'driver@demo.test',
            'name' => 'Demo Driver',
        ],
        [
            'role' => User::ROLE_COLLECTOR,
            'email' => 'collector@demo.test',
            'name' => 'Demo Collector',
        ],
        [
            'role' => User::ROLE_SKILLED_WORKER,
            'email' => 'worker@demo.test',
            'name' => 'Demo Skilled Worker',
        ],
        [
            'role' => User::ROLE_VOLUNTEER,
            'email' => 'volunteer@demo.test',
            'name' => 'Demo Volunteer',
            'volunteer_status' => VerificationVolunteer::STATUS_APPROVED,
        ],
        [
            'role' => User::ROLE_VOLUNTEER,
            'email' => 'pending.volunteer@demo.test',
            'name' => 'Demo Pending Volunteer',
            'volunteer_status' => VerificationVolunteer::STATUS_PENDING,
        ],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $account) {
            $this->ensureAccount($account);
        }

        $this->ensureAdmin();

        $this->command?->info('Demo accounts ready — password: '.self::PASSWORD);
        $this->command?->table(
            ['Email', 'Role', 'Sign-in'],
            [
                ['vendor@demo.test', 'Vendor', '/login'],
                ['driver@demo.test', 'Driver', '/login'],
                ['collector@demo.test', 'Collector', '/login'],
                ['worker@demo.test', 'Skilled worker', '/login'],
                ['volunteer@demo.test', 'Volunteer (approved)', '/login'],
                ['pending.volunteer@demo.test', 'Volunteer (pending approval)', '/login'],
                ['admin@demo.test', 'Admin', '/admin/login'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function ensureAccount(array $account): void
    {
        $email = (string) $account['email'];
        $user = User::query()->where('email_index', BlindIndex::make($email))->first();

        if ($user === null) {
            $payload = [
                'name' => $account['name'],
                'email' => $email,
                'password' => self::PASSWORD,
                'password_confirmation' => self::PASSWORD,
                'role' => $account['role'],
                // Synthetic demo fixtures use the same consent validation as members.
                'accept_terms' => true,
            ];

            // Only vendors carry these; the shared rules reject an explicit
            // null for other roles, so omit the keys entirely.
            if (! empty($account['display_name'])) {
                $payload['display_name'] = $account['display_name'];
            }

            if (! empty($account['vendor_category'])) {
                $payload['vendor_category'] = $account['vendor_category'];
            }

            $user = app(RegistrationService::class)->register($payload);
        } else {
            // The 'password' cast hashes on assignment.
            $user->forceFill([
                'name' => $account['name'],
                'password' => self::PASSWORD,
                'role' => $account['role'],
                'is_active' => true,
            ])->save();
        }

        $this->ensureRoleRow($user);
        $this->ensureVolunteerStatus($user, $account);
    }

    /**
     * Volunteers must be approved before signing in (M17.1); the demo set
     * keeps one approved and one pending so the admin queue is testable.
     *
     * @param  array<string, mixed>  $account
     */
    private function ensureVolunteerStatus(User $user, array $account): void
    {
        if ($user->role !== User::ROLE_VOLUNTEER) {
            return;
        }

        $status = $account['volunteer_status'] ?? VerificationVolunteer::STATUS_APPROVED;

        $user->verificationVolunteer?->update(['verification_status' => $status]);
    }

    private function ensureRoleRow(User $user): void
    {
        match ($user->role) {
            User::ROLE_VENDOR => Vendor::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['display_name' => 'Demo Farm', 'category' => 'agro'],
            ),
            User::ROLE_DRIVER, User::ROLE_COLLECTOR => DriverAvailability::query()->firstOrCreate(
                ['user_id' => $user->id],
            ),
            User::ROLE_VOLUNTEER => VerificationVolunteer::query()->firstOrCreate(
                ['user_id' => $user->id],
            ),
            User::ROLE_SKILLED_WORKER => WorkerProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
            ),
            default => null,
        };
    }

    private function ensureAdmin(): void
    {
        $email = 'admin@demo.test';
        $user = User::query()->where('email_index', BlindIndex::make($email))->first();

        if ($user === null) {
            User::query()->create([
                'name' => 'Demo Admin',
                'email' => $email,
                'email_index' => BlindIndex::make($email),
                'password' => self::PASSWORD,
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]);

            return;
        }

        $user->forceFill([
            'name' => 'Demo Admin',
            'password' => self::PASSWORD,
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ])->save();
    }
}
