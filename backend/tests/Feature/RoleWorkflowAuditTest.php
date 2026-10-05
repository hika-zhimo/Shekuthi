<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Models\WorkerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleWorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_public_functions_and_private_boundaries(): void
    {
        foreach (['/api/v1/catalog', '/api/v1/transport', '/api/v1/workers', '/transport', '/workers'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/api/v1/profile', '/api/v1/listings', '/api/v1/errands', '/api/v1/collector/assignment', '/api/v1/volunteer/profile'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->get('/admin/listings')->assertRedirect(route('admin.login'));
    }

    public function test_worker_contact_update_preserves_services_and_explicit_clear_is_honoured(): void
    {
        $user = User::create(['name' => 'Worker audit member', 'email' => 'worker-audit@test.example',
            'email_index' => User::emailIndex('worker-audit@test.example'), 'password' => bcrypt('Password123!'), 'role' => 'skilled_worker', 'is_active' => true]);
        $profile = WorkerProfile::create(['user_id' => $user->id, 'services' => 'Repair farm tools', 'service_areas' => 'Dimapur']);
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/profile', ['name' => 'Updated worker audit member'])->assertOk();
        $this->assertSame('Repair farm tools', $profile->fresh()->services);
        $this->assertSame('Dimapur', $profile->fresh()->service_areas);
        $this->putJson('/api/v1/profile', ['services' => null])->assertOk();
        $this->assertNull($profile->fresh()->services);
        $this->assertSame('Dimapur', $profile->fresh()->service_areas);
    }

    public function test_each_registered_role_has_a_dashboard_and_own_profile_and_admin_is_separate(): void
    {
        foreach (['vendor', 'driver', 'collector', 'volunteer', 'skilled_worker', 'admin'] as $role) {
            $email = 'audit-'.$role.'@test.example';
            $user = User::create(['name' => 'Role audit member', 'email' => $email,
                'email_index' => User::emailIndex($email), 'password' => bcrypt('Password123!'),
                'role' => $role, 'is_active' => true]);
            $user->forceFill(['email_verified_at' => now()])->save();
            if ($role === 'vendor') {
                Vendor::create(['user_id' => $user->id, 'display_name' => 'Role audit shop', 'category' => 'agro']);
            }
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/profile')->assertOk()->assertJsonPath('user.role', $role);
            $this->getJson('/api/v1/consents')->assertOk();
            $this->getJson('/api/v1/notifications')->assertOk();
            $this->actingAs($user)->get('/dashboard')->assertOk();
            $response = $this->get('/admin/listings');
            if ($role === 'admin') {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
            if ($role !== 'driver') {
                $this->actingAs($user, 'sanctum')->putJson('/api/v1/driver/availability', ['is_online' => true])->assertUnprocessable();
            }
            if (! in_array($role, ['vendor', 'admin'], true)) {
                $this->actingAs($user, 'sanctum')->postJson('/api/v1/listings', ['title' => 'Role boundary listing', 'category' => 'agro'])->assertForbidden();
            }
            if ($role !== 'volunteer') {
                $this->actingAs($user, 'sanctum')->postJson('/api/v1/volunteer/profile', [])->assertUnprocessable();
            }
            $this->actingAs($user, 'sanctum')->putJson('/api/v1/profile', ['name' => 'Updated role audit member'])->assertOk()->assertJsonPath('user.name', 'Updated role audit member');
        }
    }
}
