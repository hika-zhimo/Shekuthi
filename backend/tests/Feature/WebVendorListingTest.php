<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Support\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Website vendor listing management (M12.1): the reported bug was that a
 * vendor registering on the website had no way to create a listing.
 */
class WebVendorListingTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $email): User
    {
        return User::query()->create([
            'name' => 'Test '.$role,
            'email' => $email,
            'email_index' => BlindIndex::make($email),
            'password' => bcrypt('Password123!'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function makeVendor(string $email = 'webvendor@test.com'): User
    {
        $user = $this->makeUser(User::ROLE_VENDOR, $email);

        Vendor::query()->create([
            'user_id' => $user->id,
            'display_name' => 'Web Shop',
            'category' => 'traditional',
        ]);

        return $user->fresh();
    }

    public function test_a_vendor_can_open_the_create_form_from_the_dashboard(): void
    {
        $this->actingAs($this->makeVendor())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('vendor.listings.create'), false);

        $this->actingAs($this->makeVendor('form@test.com'))
            ->get(route('vendor.listings.create'))
            ->assertOk()
            ->assertSee('New listing');
    }

    public function test_a_vendor_can_create_a_listing_from_the_website(): void
    {
        $this->actingAs($this->makeVendor())
            ->post(route('vendor.listings.store'), [
                'title' => 'Web Basket',
                'category' => 'traditional',
                'description' => 'Woven by hand.',
                'price' => 120,
                'unit' => 'piece',
                'moq' => 2,
                'status' => 'active',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('products', [
            'title' => 'Web Basket',
            'category' => 'traditional',
            'status' => 'pending',
            'moq' => 2,
        ]);
    }

    public function test_a_vendor_can_create_a_listing_with_photos(): void
    {
        Storage::fake('public');
        $user = $this->makeVendor('photos@test.com');

        $this->actingAs($user)
            ->post(route('vendor.listings.store'), [
                'title' => 'Photo Basket',
                'category' => 'agro',
                'price' => 100,
                'moq' => 1,
                'status' => 'draft',
                'photos' => [UploadedFile::fake()->image('basket.png', 320, 240)],
            ])
            ->assertRedirect(route('dashboard'));

        $product = Product::query()->where('title', 'Photo Basket')->firstOrFail();

        $this->assertCount(1, $product->images);
        $this->assertStringEndsWith('.webp', $product->images[0]);
        $this->assertDatabaseHas('media', [
            'path' => $product->images[0],
            'mime_type' => 'image/webp',
            'uploaded_by' => $user->id,
        ]);
    }

    public function test_a_vendor_can_edit_their_listing_and_remove_a_photo(): void
    {
        $user = $this->makeVendor('edit@test.com');

        $product = $user->vendor->products()->create([
            'title' => 'Old Title',
            'category' => 'traditional',
            'price' => 50,
            'moq' => 1,
            'status' => Product::STATUS_DRAFT,
            'images' => ['products/keep.webp', 'products/remove.webp'],
        ]);

        $this->actingAs($user)
            ->put(route('vendor.listings.update', $product), [
                'title' => 'New Title',
                'category' => 'traditional',
                'price' => 75,
                'moq' => 1,
                'status' => 'active',
                'remove_photos' => ['products/remove.webp'],
            ])
            ->assertRedirect(route('dashboard'));

        $product->refresh();

        $this->assertSame('New Title', $product->title);
        $this->assertSame(['products/keep.webp'], $product->images);
        $this->assertSame(Product::STATUS_PENDING, $product->status);
    }

    public function test_rental_listings_require_a_date_window_and_price(): void
    {
        $this->actingAs($this->makeVendor('rental@test.com'))
            ->post(route('vendor.listings.store'), [
                'title' => 'Hill Homestay',
                'category' => 'rental_homestay',
                'status' => 'draft',
            ])
            ->assertSessionHasErrors(['price', 'available_from', 'available_to']);
    }

    public function test_a_non_vendor_cannot_create_or_edit_listings(): void
    {
        $volunteer = $this->makeUser(User::ROLE_VOLUNTEER, 'vol@test.com');

        $this->actingAs($volunteer)
            ->get(route('vendor.listings.create'))
            ->assertForbidden();

        $this->actingAs($volunteer)
            ->post(route('vendor.listings.store'), [
                'title' => 'Nope',
                'category' => 'agro',
            ])
            ->assertForbidden();
    }

    public function test_a_vendor_cannot_edit_another_vendors_listing(): void
    {
        $owner = $this->makeVendor('owner@test.com');
        $other = $this->makeVendor('other@test.com');

        $product = $owner->vendor->products()->create([
            'title' => 'Owned',
            'category' => 'agro',
            'price' => 10,
            'moq' => 1,
            'status' => Product::STATUS_DRAFT,
        ]);

        $this->actingAs($other)
            ->get(route('vendor.listings.edit', $product))
            ->assertForbidden();
    }

    /**
     * M12.2: publish, pause and archive straight from the listings table.
     */
    public function test_a_vendor_can_publish_pause_and_archive_from_the_dashboard(): void
    {
        $user = $this->makeVendor('status@test.com');

        $product = $user->vendor->products()->create([
            'title' => 'Status Basket',
            'category' => 'agro',
            'price' => 10,
            'moq' => 1,
            'status' => Product::STATUS_DRAFT,
        ]);

        $this->actingAs($user)
            ->put(route('vendor.listings.status', $product), ['status' => 'active'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame(Product::STATUS_PENDING, $product->fresh()->status);

        $this->actingAs($user)
            ->put(route('vendor.listings.status', $product), ['status' => 'inactive'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame(Product::STATUS_INACTIVE, $product->fresh()->status);

        $this->actingAs($user)
            ->put(route('vendor.listings.status', $product), ['status' => 'archived'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame(Product::STATUS_ARCHIVED, $product->fresh()->status);
    }

    public function test_the_status_change_rejects_unknown_values(): void
    {
        $user = $this->makeVendor('badstatus@test.com');

        $product = $user->vendor->products()->create([
            'title' => 'Kept',
            'category' => 'agro',
            'price' => 10,
            'moq' => 1,
            'status' => Product::STATUS_DRAFT,
        ]);

        $this->actingAs($user)
            ->put(route('vendor.listings.status', $product), ['status' => 'deleted'])
            ->assertSessionHasErrors('status');

        $this->assertSame(Product::STATUS_DRAFT, $product->fresh()->status);
    }

    public function test_a_vendor_cannot_change_another_vendors_listing_status(): void
    {
        $owner = $this->makeVendor('statusowner@test.com');
        $other = $this->makeVendor('statusother@test.com');

        $product = $owner->vendor->products()->create([
            'title' => 'Not Yours',
            'category' => 'agro',
            'price' => 10,
            'moq' => 1,
            'status' => Product::STATUS_DRAFT,
        ]);

        $this->actingAs($other)
            ->put(route('vendor.listings.status', $product), ['status' => 'active'])
            ->assertForbidden();
    }
}
