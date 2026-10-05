<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\CollectorAssignment;
use App\Models\Consent;
use App\Models\DataRequest;
use App\Models\DeviceToken;
use App\Models\District;
use App\Models\DriverAvailability;
use App\Models\Errand;
use App\Models\Locality;
use App\Models\LogisticsJob;
use App\Models\Media;
use App\Models\PasswordChangeOtp;
use App\Models\Post;
use App\Models\Product;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\RiderBaseOperation;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Verification;
use App\Models\VerificationVolunteer;
use App\Models\WorkerProfile;
use App\Services\DataDeletionService;
use App\Support\BlindIndex;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountDataLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        $phone = '+91987654321'.User::query()->count();

        return User::query()->create(['name' => 'Account fixture', 'email' => $email,
            'email_index' => BlindIndex::make($email), 'phone' => $phone,
            'phone_index' => BlindIndex::make($phone), 'password' => bcrypt('FixturePassword123!'),
            'role' => User::ROLE_VENDOR, 'is_active' => true]);
    }

    /** Includes historical role records so erasure does not depend on current role. */
    private function fixture(): array
    {
        Storage::fake('public');
        Storage::fake('local');
        $owner = $this->user('owner@fixture.test');
        $other = $this->user('other@fixture.test');
        $district = District::create(['name' => 'Fixture district', 'is_active' => true]);
        $locality = Locality::create(['name' => 'Fixture locality', 'district_id' => $district->id, 'is_active' => true]);
        $vendor = Vendor::create(['user_id' => $owner->id, 'display_name' => 'Private workshop',
            'category' => 'traditional', 'address' => 'Private workshop address',
            'district_id' => $district->id, 'locality_id' => $locality->id]);
        $otherVendor = Vendor::create(['user_id' => $other->id, 'display_name' => 'Other workshop', 'category' => 'agro']);
        $product = Product::create(['vendor_id' => $vendor->id, 'category' => 'traditional',
            'title' => 'Workshop goods', 'description' => 'Owner authored description', 'status' => 'active',
            'price' => 50, 'moq' => 1, 'images' => ['products/owner.webp']]);
        $otherProduct = Product::create(['vendor_id' => $otherVendor->id, 'category' => 'agro',
            'title' => 'Other account goods', 'status' => 'active', 'images' => ['products/other.webp']]);
        $worker = WorkerProfile::create(['user_id' => $owner->id, 'services' => 'Private work notes', 'service_areas' => 'Private address']);
        $volunteer = VerificationVolunteer::create(['user_id' => $owner->id,
            'availability' => 'Private availability', 'tada_notes' => 'Private travel notes', 'photo_path' => 'avatars/owner.webp']);
        $report = Verification::create(['volunteer_id' => $volunteer->id, 'subject_type' => Vendor::class,
            'subject_id' => $otherVendor->id, 'notes' => 'Private visit notes',
            'evidence' => ['evidence/owner.webp'], 'geo_lat' => 25.9, 'geo_lng' => 93.7, 'status' => 'approved']);
        $base = RiderBaseOperation::create(['user_id' => $owner->id, 'district_id' => $district->id]);
        $base->localities()->attach($locality->id);
        DriverAvailability::create(['user_id' => $owner->id, 'is_online' => true]);
        CollectorAssignment::create(['user_id' => $owner->id, 'locality_id' => $locality->id,
            'is_active' => true, 'assigned_by' => $other->id, 'assigned_at' => now()]);
        $booking = Booking::create(['code' => 'BK-PRIV01', 'vendor_id' => $vendor->id, 'status' => 'completed',
            'contact_name' => 'Other guest contact', 'contact_phone' => $owner->phone,
            'contact_phone_index' => $owner->phone_index, 'notes' => 'Guest free text', 'settled_offline' => true]);
        BookingItem::create(['booking_id' => $booking->id, 'product_id' => $product->id,
            'quantity' => 2, 'unit_price_snapshot' => 50]);
        $errandFields = ['description' => 'Private errand details', 'pickup_district_id' => $district->id,
            'pickup_locality_id' => $locality->id, 'drop_district_id' => $district->id,
            'drop_locality_id' => $locality->id, 'contact_name' => 'Owner errand contact',
            'contact_phone' => $owner->phone, 'contact_phone_index' => $owner->phone_index,
            'pickup_address' => 'Private pickup address', 'drop_address' => 'Private drop address'];
        $errand = Errand::create([...$errandFields, 'code' => 'ER-OWN01', 'customer_id' => $owner->id,
            'driver_id' => $other->id, 'status' => 'completed']);
        $driverErrand = Errand::create([...$errandFields, 'code' => 'ER-OTHER1', 'customer_id' => $other->id,
            'contact_name' => 'Other customer', 'driver_id' => $owner->id, 'status' => 'accepted']);
        $job = LogisticsJob::create(['vendor_id' => $otherVendor->id, 'driver_id' => $owner->id,
            'collector_id' => $other->id, 'type' => 'delivery', 'district_id' => $district->id,
            'locality_id' => $locality->id, 'address' => 'Other customer address', 'status' => 'completed']);
        $post = Post::create(['title' => 'Owner visit story', 'slug' => 'owner-visit-story',
            'body' => 'Private story notes', 'author_id' => $owner->id, 'vendor_id' => $otherVendor->id,
            'cover_image' => 'evidence/owner.webp', 'images' => ['evidence/owner.webp'],
            'status' => 'published', 'published_at' => now()]);
        $otherPost = Post::create(['title' => 'Other story', 'slug' => 'other-story', 'body' => 'Other account content',
            'author_id' => $other->id, 'vendor_id' => $otherVendor->id, 'status' => 'published',
            'published_at' => now(), 'images' => ['evidence/owner.webp', 'products/other.webp']]);
        $badge = Badge::create(['subject_type' => Vendor::class, 'subject_id' => $otherVendor->id,
            'volunteer_name' => 'Immutable attribution', 'volunteer_photo' => 'avatars/owner.webp',
            'post_id' => $post->id, 'issued_at' => now()]);
        $referral = Referral::create(['owner_user_id' => $owner->id, 'vendor_id' => $vendor->id,
            'code' => 'OWNER-CODE', 'commission_type' => 'percent', 'commission_value' => 5]);
        $event = ReferralEvent::create(['referral_id' => $referral->id, 'attributed_user_id' => $other->id,
            'type' => 'conversion', 'status' => 'approved', 'amount_inr' => 12.5,
            'approved_by' => $owner->id, 'metadata' => ['contact' => 'Private metadata']]);
        foreach ([User::class => $owner->id, Product::class => $product->id,
            Verification::class => $report->id, Errand::class => $errand->id] as $type => $id) {
            Consent::create(['subject_type' => $type, 'subject_id' => $id, 'consent_key' => 'fixture',
                'text_version' => '1.0', 'purpose' => 'Fixture permission', 'granted_at' => now()]);
        }
        Consent::create(['subject_type' => User::class, 'subject_id' => $other->id,
            'consent_key' => 'other-fixture', 'text_version' => '1.0', 'purpose' => 'Other permission', 'granted_at' => now()]);
        foreach (['products/owner.webp', 'avatars/owner.webp', 'evidence/owner.webp'] as $path) {
            Storage::disk('public')->put($path, 'Owner binary fixture');
            Media::create(['uploaded_by' => $owner->id, 'path' => $path, 'mime_type' => 'image/webp', 'size' => 20]);
        }
        Storage::disk('public')->put('products/other.webp', 'Other binary fixture');
        Media::create(['uploaded_by' => $other->id, 'path' => 'products/other.webp', 'size' => 20]);
        DeviceToken::create(['user_id' => $owner->id, 'token' => 'SECRET_PUSH_TOKEN', 'platform' => 'android']);
        $accessToken = $owner->createToken('Fixture device')->plainTextToken;
        PasswordChangeOtp::create(['user_id' => $owner->id, 'otp_hash' => 'SECRET_OTP_HASH',
            'new_password_hash' => 'SECRET_NEW_PASSWORD', 'expires_at' => now()->addMinutes(5)]);
        DB::table('sessions')->insert(['id' => 'SECRET_SESSION_COOKIE', 'user_id' => $owner->id,
            'ip_address' => '192.0.2.1', 'user_agent' => 'Fixture device', 'payload' => 'SECRET_SESSION_PAYLOAD', 'last_activity' => now()->timestamp]);
        DB::table('password_reset_tokens')->insert(['email' => $owner->email, 'token' => 'SECRET_RESET_TOKEN', 'created_at' => now()]);
        $owner->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'fixture',
            'data' => ['title' => 'Account notice', 'body' => 'Own notice']]);

        return compact('owner', 'other', 'vendor', 'product', 'otherProduct', 'volunteer', 'report', 'booking',
            'errand', 'driverErrand', 'job', 'post', 'otherPost', 'badge', 'referral', 'event', 'accessToken');
    }

    public function test_legacy_authored_blog_uploads_are_exported_and_erased_without_touching_other_authors(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->user('story-owner@fixture.test');
        $other = $this->user('story-other@fixture.test');
        Post::create(['author_id' => $owner->id, 'title' => 'Own story', 'slug' => 'own-story',
            'body' => 'Own story content', 'cover_image' => 'blog/own.webp']);
        Post::create(['author_id' => $other->id, 'title' => 'Other story', 'slug' => 'other-story',
            'body' => 'Other story content', 'cover_image' => 'blog/other.webp']);
        Storage::disk('public')->put('blog/own.webp', 'Own cover');
        Storage::disk('public')->put('blog/other.webp', 'Other cover');
        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/export')->assertCreated();
        $export = DataRequest::findOrFail($response->json('data.request_id'));
        $data = json_decode(Storage::disk('local')->get(substr($export->notes, strlen('private:'))), true);
        $this->assertSame('Own cover', base64_decode($data['media'][0]['contents_base64']));
        $this->postJson('/api/v1/deletion')->assertCreated();
        Storage::disk('public')->assertMissing('blog/own.webp');
        Storage::disk('public')->assertExists('blog/other.webp');
    }

    public function test_file_removal_failure_leaves_deletion_retryable_and_access_disabled(): void
    {
        $f = $this->fixture();
        $public = Storage::disk('public');
        $local = Storage::disk('local');
        $broken = \Mockery::mock(FilesystemAdapter::class);
        $broken->shouldReceive('exists')->once()->andReturn(true);
        $broken->shouldReceive('delete')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($broken, $public);
        Storage::shouldReceive('disk')->with('local')->andReturn($local);
        $request = DataRequest::create(['user_id' => $f['owner']->id, 'type' => 'deletion',
            'status' => 'processing', 'requested_at' => now()]);
        try {
            app(DataDeletionService::class)->delete($f['owner'], $request);
            $this->fail('Storage failure must not mark deletion complete.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('retry', $exception->getMessage());
        }
        $this->assertSame('processing', $request->fresh()->status);
        $this->assertFalse($f['owner']->fresh()->is_active);
        $this->assertSame(0, $f['owner']->tokens()->count());
        $this->assertDatabaseHas('media', ['uploaded_by' => $f['owner']->id]);
        app(DataDeletionService::class)->delete($f['owner']->fresh(), $request);
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertDatabaseMissing('media', ['uploaded_by' => $f['owner']->id]);
        $public->assertExists('products/other.webp');
    }

    public function test_export_is_complete_private_and_excludes_other_participants_and_secrets(): void
    {
        $f = $this->fixture();
        $response = $this->actingAs($f['owner'], 'sanctum')->postJson('/api/v1/export')->assertCreated();
        $request = DataRequest::findOrFail($response->json('data.request_id'));
        $json = Storage::disk('local')->get(substr($request->notes, strlen('private:')));
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('owner@fixture.test', $data['user']['email']);
        $this->assertSame('Private travel notes', $data['profiles']['volunteer']['tada_notes']);
        $this->assertSame('Private work notes', $data['profiles']['worker']['services']);
        foreach (['listings', 'bookings', 'logistics_jobs', 'verifications', 'referrals', 'referral_events',
            'posts', 'badges', 'notifications', 'devices', 'access_tokens', 'sessions', 'password_changes', 'data_requests'] as $key) {
            $this->assertCount(1, $data[$key], $key);
        }
        $this->assertCount(2, $data['errands']);
        $this->assertCount(3, $data['media']);
        $this->assertSame('Owner binary fixture', base64_decode($data['media'][0]['contents_base64']));
        $this->assertCount(4, $data['consents']);
        $this->assertCount(1, $data['bookings'][0]['items']);
        foreach (['other@fixture.test', 'Other guest contact', 'Other customer address',
            'Private metadata', 'Other account goods', 'Other binary fixture',
            'SECRET_PUSH_TOKEN', 'SECRET_OTP_HASH', 'SECRET_NEW_PASSWORD',
            'SECRET_SESSION_COOKIE', 'SECRET_SESSION_PAYLOAD', 'SECRET_RESET_TOKEN', $f['accessToken'], $f['owner']->password] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertSame([], Storage::disk('public')->allFiles('exports'));
        $this->assertStringContainsString('signature=', $response->json('data.download_url'));
    }

    public function test_deletion_erases_role_activity_media_and_exports_and_preserves_other_accounts(): void
    {
        $f = $this->fixture();
        $export = $this->actingAs($f['owner'], 'sanctum')->postJson('/api/v1/export')->assertCreated();
        $exportRequest = DataRequest::findOrFail($export->json('data.request_id'));
        $privatePath = substr($exportRequest->notes, strlen('private:'));
        Storage::disk('local')->put('exports/superseded.json', json_encode(['user' => ['id' => $f['owner']->id]]));
        Storage::disk('public')->put('exports/legacy.json', json_encode(['user' => ['id' => $f['owner']->id]]));
        Storage::disk('local')->put('exports/other.json', json_encode(['user' => ['id' => $f['other']->id]]));
        $response = $this->postJson('/api/v1/deletion')->assertCreated()->assertJsonPath('data.status', 'completed');
        $owner = $f['owner']->fresh();
        $this->assertFalse($owner->is_active);
        $this->assertSame('Deleted User', $owner->name);
        $this->assertNull($owner->phone);
        $this->assertNull($owner->district_id);
        $this->assertSame(0, $owner->tokens()->count());
        foreach (['device_tokens', 'password_change_otps', 'sessions', 'worker_profiles',
            'verification_volunteers', 'driver_availability', 'rider_base_operations', 'collector_assignments'] as $table) {
            $this->assertDatabaseMissing($table, ['user_id' => $owner->id]);
        }
        $this->assertSame(0, $owner->notifications()->count());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'owner@fixture.test']);
        $this->assertDatabaseMissing('media', ['uploaded_by' => $owner->id]);
        $this->assertDatabaseCount('consents', 1);
        $this->assertSame('archived', $f['product']->fresh()->status);
        $this->assertSame([], $f['product']->fresh()->images);
        $this->assertNull($f['vendor']->fresh()->address);
        $this->assertSame('Other guest contact', $f['booking']->fresh()->contact_name);
        $this->assertNull($f['errand']->fresh()->contact_phone);
        $this->assertNull($f['errand']->fresh()->pickup_address);
        $this->assertSame('completed', $f['errand']->fresh()->status);
        $this->assertSame('Other customer', $f['driverErrand']->fresh()->contact_name);
        $this->assertNull($f['driverErrand']->fresh()->driver_id);
        $this->assertNull($f['job']->fresh()->driver_id);
        $this->assertSame($f['other']->id, $f['job']->fresh()->collector_id);
        $this->assertSame('completed', $f['job']->fresh()->status);
        $this->assertSame('draft', $f['post']->fresh()->status);
        $this->assertSame('Immutable attribution', $f['badge']->fresh()->volunteer_name);
        $this->assertNull($f['badge']->fresh()->volunteer_photo);
        $this->assertSame(['products/other.webp'], $f['otherPost']->fresh()->images);
        $this->assertSame('Other account content', $f['otherPost']->fresh()->body);
        $this->assertSame('Other account goods', $f['otherProduct']->fresh()->title);
        $this->assertSame('12.50', $f['event']->fresh()->amount_inr);
        $this->assertNull($f['event']->fresh()->metadata);
        $this->assertNull($f['referral']->fresh()->owner_user_id);
        $this->assertSame('other@fixture.test', $f['other']->fresh()->email);
        Storage::disk('public')->assertMissing(['products/owner.webp', 'avatars/owner.webp', 'evidence/owner.webp', 'exports/legacy.json']);
        Storage::disk('public')->assertExists('products/other.webp');
        Storage::disk('local')->assertMissing([$privatePath, 'exports/superseded.json']);
        Storage::disk('local')->assertExists('exports/other.json');
        $this->assertNull($exportRequest->fresh()->notes);
        $parts = parse_url($export->json('data.download_url'));
        $this->get($parts['path'].'?'.$parts['query'])->assertNotFound();
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/auth/me')->assertForbidden();
        $this->actingAs($owner)->get('/dashboard')->assertForbidden();
        app(DataDeletionService::class)->delete($owner, DataRequest::findOrFail($response->json('data.request_id')));
        $this->assertSame('Other account goods', $f['otherProduct']->fresh()->title);
    }
}
