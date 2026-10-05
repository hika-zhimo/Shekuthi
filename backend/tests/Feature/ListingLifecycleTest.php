<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\ListingExpiryNotification;
use App\Services\ListingLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Product $product;

    private ListingLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2024, 2, 29)->startOfDay());
        $this->owner = $this->user(User::ROLE_VENDOR, 'lifecycle-vendor@test.example');
        $this->admin = $this->user(User::ROLE_ADMIN, 'lifecycle-admin@test.example');
        $vendor = Vendor::query()->create(['user_id' => $this->owner->id, 'display_name' => 'Lifecycle Farm', 'category' => 'agro']);
        $this->product = $vendor->products()->create(['title' => 'Seasonal grain', 'description' => 'Local harvest', 'category' => 'agro', 'price' => 40, 'moq' => 1, 'images' => [], 'status' => 'pending']);
        $this->service = app(ListingLifecycleService::class);
    }

    private function user(string $role, string $email): User
    {
        $user = User::query()->create(['name' => 'Lifecycle member', 'email' => $email, 'email_index' => User::emailIndex($email), 'password' => bcrypt('Password123!'), 'role' => $role, 'is_active' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function approve(): void
    {
        $this->service->approve($this->product);
        $this->product->refresh();
    }

    public function test_approval_is_mandatory_even_with_flag_disabled_and_for_admin_creation(): void
    {
        config(['app.require_listing_approval' => false]);
        foreach ([$this->owner, $this->admin] as $user) {
            $this->actingAs($user, 'sanctum')->postJson('/api/v1/listings', ['title' => 'Review required', 'category' => 'agro', 'status' => 'active'])
                ->assertCreated()->assertJsonPath('data.status', 'pending');
        }
        $this->approve();
        $this->assertSame('2025-02-28', $this->product->expires_at->toDateString());
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/listings/'.$this->product->id, ['status' => 'inactive'])->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->actingAs($this->admin, 'sanctum')->putJson('/api/v1/listings/'.$this->product->id, ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_expiry_hides_catalog_detail_and_blocks_booking_before_cron(): void
    {
        $this->approve();
        $this->travelTo($this->product->expires_at->copy()->subSecond());
        $this->assertTrue($this->product->isLive());
        $this->assertSame(1, Product::active()->count());
        $this->travel(1)->seconds();
        $this->assertFalse($this->product->isLive());
        $this->assertSame(0, Product::active()->count());
        $this->getJson('/api/v1/catalog/'.$this->product->id)->assertNotFound();
        $this->get('/listings/'.$this->product->id)->assertNotFound();
        $this->postJson('/api/v1/bookings', ['items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'contact_name' => 'Grain buyer', 'contact_phone' => '9876543210', 'accept_contact' => true])->assertUnprocessable();
    }

    public function test_warning_schedule_is_idempotent_and_deletion_preserves_orders_and_removes_files(): void
    {
        Storage::fake('public');
        Notification::fake();
        Storage::disk('public')->put('products/season.webp', 'test image');
        $this->product->update(['images' => ['products/season.webp']]);
        $booking = Booking::query()->create(['vendor_id' => $this->product->vendor_id, 'code' => 'LIFE-01', 'status' => 'completed', 'contact_name' => 'Grain buyer', 'contact_phone' => '9876543210', 'contact_phone_index' => User::phoneIndex('9876543210')]);
        $item = BookingItem::query()->create(['booking_id' => $booking->id, 'product_id' => $this->product->id, 'quantity' => 2, 'unit_price_snapshot' => 40]);
        $this->approve();
        $this->travelTo($this->product->expires_at);
        $this->assertSame(1, $this->service->sweep()['expired']);
        $this->assertSame('inactive', $this->product->fresh()->status);
        Notification::assertSentToTimes($this->owner, ListingExpiryNotification::class, 2);
        $this->service->sweep();
        Notification::assertSentToTimes($this->owner, ListingExpiryNotification::class, 2);
        $this->travel(23)->days();
        $this->service->sweep();
        Notification::assertSentToTimes($this->owner, ListingExpiryNotification::class, 4);
        $this->travel(7)->days();
        $this->assertSame(1, $this->service->sweep(true)['deleted']);
        $this->assertNotNull(Product::find($this->product->id));
        $this->assertSame(1, $this->service->sweep()['deleted']);
        $this->assertNull(Product::find($this->product->id));
        $tombstone = Product::withTrashed()->findOrFail($this->product->id);
        $this->assertSame('Deleted listing', $tombstone->title);
        $this->assertNull($tombstone->description);
        $this->assertSame([], $tombstone->images);
        $this->assertSame('40.00', $item->fresh()->unit_price_snapshot);
        $this->assertSame('Deleted listing', $item->fresh()->product->title);
        Storage::disk('public')->assertMissing('products/season.webp');
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/listings/'.$this->product->id.'/renew')->assertNotFound();
    }

    public function test_late_scheduler_defers_deletion_for_seven_days_and_dry_run_is_inert(): void
    {
        Notification::fake();
        $this->approve();
        $this->travelTo($this->product->expires_at->copy()->addDays(40));
        $this->artisan('listings:lifecycle --dry-run')->assertExitCode(0);
        $this->assertSame('active', $this->product->fresh()->status);
        Notification::assertNothingSent();
        $this->service->sweep();
        $this->assertTrue($this->product->fresh()->deletion_scheduled_at->equalTo(now()->addDays(7)));
        $this->assertNotNull(Product::find($this->product->id));
        $this->travel(7)->days();
        $this->assertSame(1, $this->service->sweep()['deleted']);
    }

    public function test_channel_failure_retries_only_failed_channel_and_prevents_deletion(): void
    {
        $this->approve();
        $this->travelTo($this->product->expires_at);
        Notification::shouldReceive('sendNow')->withArgs(fn ($user, $notification, $channels) => $channels === ['mail'])->andThrow(new \RuntimeException('Transport unavailable'));
        Notification::shouldReceive('sendNow')->withArgs(fn ($user, $notification, $channels) => $channels === ['database'])->andReturnNull();
        $this->assertSame(1, $this->service->sweep()['failed']);
        $this->assertNull($this->product->fresh()->expiry_mail_sent_at);
        $this->assertNotNull($this->product->fresh()->expiry_database_sent_at);
        $this->travel(31)->days();
        $this->assertSame(0, $this->service->sweep()['deleted']);
        Notification::fake();
        $this->service->sweep();
        Notification::assertSentToTimes($this->owner, ListingExpiryNotification::class, 2);
        $this->travel(7)->days();
        $this->assertSame(1, $this->service->sweep()['deleted']);
    }

    public function test_renewal_requires_ownership_and_review_then_restarts_year(): void
    {
        Notification::fake();
        $this->approve();
        $this->travelTo($this->product->expires_at);
        $this->service->sweep();
        $other = $this->user(User::ROLE_VENDOR, 'other-vendor@test.example');
        $this->postJson('/api/v1/listings/'.$this->product->id.'/renew')->assertUnauthorized();
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/listings/'.$this->product->id.'/renew')->assertForbidden();
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/listings/'.$this->product->id.'/renew')->assertOk()->assertJsonPath('data.status', 'pending');
        $this->travel(40)->days();
        $this->assertSame(0, $this->service->sweep()['deleted']);
        $this->service->approve($this->product);
        $product = $this->product->fresh();
        $this->assertTrue($product->isLive());
        $this->assertTrue($product->expires_at->equalTo(now()->addYearNoOverflow()));
        $this->assertNull($product->deletion_scheduled_at);
        $this->assertNull($product->expiry_mail_sent_at);
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/listings/'.$this->product->id.'/renew')->assertUnprocessable();
    }

    public function test_rejected_renewal_gets_new_warning_window_and_web_renewal_is_available(): void
    {
        Notification::fake();
        $this->approve();
        $this->travelTo($this->product->expires_at);
        $this->service->sweep();
        $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertSee('Renew for admin review')->assertSee('Renew before deletion');
        $this->actingAs($this->owner)->post(route('vendor.listings.renew', $this->product))->assertRedirect(route('dashboard'));
        $this->travel(20)->days();
        $this->actingAs($this->admin)->post(route('admin.listings.reject', $this->product))->assertRedirect();
        $this->assertTrue($this->product->fresh()->deletion_scheduled_at->equalTo(now()->addDays(30)));
        $this->assertNull($this->product->fresh()->expiry_mail_sent_at);
        $this->service->sweep();
        $this->assertNotNull($this->product->fresh()->expiry_mail_sent_at);
    }

    public function test_renewal_endpoint_is_rate_limited(): void
    {
        $this->approve();
        $this->travelTo($this->product->expires_at);
        $this->actingAs($this->owner, 'sanctum');
        $url = '/api/v1/listings/'.$this->product->id.'/renew';
        $this->postJson($url)->assertOk();
        for ($attempt = 1; $attempt < 30; $attempt++) {
            $this->postJson($url)->assertUnprocessable();
        }
        $this->postJson($url)->assertStatus(429);
    }

    public function test_web_form_explains_mandatory_approval_without_a_misleading_status_selector(): void
    {
        $this->actingAs($this->owner)->get(route('vendor.listings.create'))->assertOk()
            ->assertSee('Every new listing and content change needs admin approval')
            ->assertSee('Submit for admin approval')->assertDontSee('listing-status');
    }

    public function test_migration_requires_review_for_legacy_active_listings(): void
    {
        $migration = require database_path('migrations/2026_10_05_000001_add_listing_lifecycle_to_products.php');
        $migration->down();
        DB::table('products')->where('id', $this->product->id)->update(['status' => 'active']);
        $migration->up();
        $this->assertSame('pending', $this->product->fresh()->status);
        $this->assertNull($this->product->fresh()->approved_at);
    }

    public function test_shared_files_survive_and_an_unavailable_owner_blocks_automatic_deletion(): void
    {
        Notification::fake();
        Storage::fake('public');
        Storage::disk('public')->put('products/shared.webp', 'Shared image');
        $this->product->update(['images' => ['products/shared.webp']]);
        $this->product->vendor->products()->create(['title' => 'Another grain listing', 'category' => 'agro', 'status' => 'pending', 'images' => ['products/shared.webp']]);
        $this->approve();
        $this->travelTo($this->product->expires_at);
        $this->owner->update(['is_active' => false]);
        $this->assertSame(1, $this->service->sweep()['failed']);
        $this->artisan('listings:lifecycle')->assertExitCode(1);
        Notification::assertNothingSent();
        $this->owner->update(['is_active' => true]);
        $this->service->sweep();
        $this->travel(23)->days();
        $this->service->sweep();
        $this->travel(7)->days();
        $this->assertSame(1, $this->service->sweep()['deleted']);
        Storage::disk('public')->assertExists('products/shared.webp');
    }

    public function test_notification_writes_existing_inbox_and_builds_email_without_external_delivery(): void
    {
        $this->approve();
        $this->travelTo($this->product->expires_at);
        $this->assertSame(2, $this->service->sweep()['warnings']);
        $notice = $this->owner->notifications()->firstOrFail();
        $this->assertSame($this->product->id, $notice->data['listing_id']);
        $this->assertStringContainsString('Seasonal grain', $notice->data['body']);
        $notification = new ListingExpiryNotification($this->product->id, 'Seasonal grain', '2025-03-30 UTC', true);
        $this->assertSame(['mail', 'database'], $notification->via($this->owner));
        $this->assertSame(route('dashboard'), $notification->toMail($this->owner)->actionUrl);
    }
}
