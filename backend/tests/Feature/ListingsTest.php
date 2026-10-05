<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Locality;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingsTest extends TestCase
{
    use RefreshDatabase;

    private function registerVendor(string $category = 'agro', string $email = 'vendor@example.test'): array
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Meera Devi',
            'email' => $email,
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'accept_terms' => true,
            'role' => 'vendor',
            'display_name' => 'Meera Organic Farm',
            'vendor_category' => $category,
        ]);

        $response->assertCreated();

        return ['token' => (string) $response->json('token')];
    }

    public function test_a_vendor_can_create_an_agro_listing(): void
    {
        ['token' => $token] = $this->registerVendor();

        $response = $this->withToken($token)->postJson('/api/v1/listings', [
            'title' => 'Fresh tomatoes',
            'category' => 'agro',
            'description' => 'Picked the same morning, sold by the kilogram.',
            'price' => 24.50,
            'unit' => 'kg',
            'moq' => 5,
            'stock' => 120,
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Fresh tomatoes')
            ->assertJsonPath('data.moq', 5)
            ->assertJsonPath('data.is_verified', false);

        $this->assertDatabaseHas('products', ['title' => 'Fresh tomatoes', 'status' => 'pending']);
    }

    public function test_a_listing_accepts_at_most_four_images(): void
    {
        ['token' => $token] = $this->registerVendor();

        $images = array_map(
            fn (int $number): string => "products/photo{$number}.webp",
            range(1, Product::MAX_IMAGES + 1),
        );

        $this->withToken($token)->postJson('/api/v1/listings', [
            'title' => 'Too many photos',
            'category' => 'agro',
            'images' => $images,
        ])->assertUnprocessable()->assertJsonValidationErrors('images');
    }

    public function test_a_rental_listing_requires_the_availability_window_and_forbids_moq(): void
    {
        ['token' => $token] = $this->registerVendor('rental_homestay', 'stay@example.test');

        $rejected = $this->withToken($token)->postJson('/api/v1/listings', [
            'title' => 'Riverside homestay room',
            'category' => 'rental_homestay',
            'price' => 1200,
            'unit' => 'night',
            'moq' => 2,
            'status' => 'active',
        ]);

        $rejected->assertUnprocessable()->assertJsonValidationErrors(['moq', 'available_from', 'available_to']);

        $ok = $this->withToken($token)->postJson('/api/v1/listings', [
            'title' => 'Riverside homestay room',
            'category' => 'rental_homestay',
            'price' => 1200,
            'unit' => 'night',
            'available_from' => '2026-10-01',
            'available_to' => '2026-10-01',
            'status' => 'active',
        ]);

        $ok->assertCreated()->assertJsonPath('data.category', 'rental_homestay');
    }

    public function test_a_vendor_cannot_update_or_delete_another_vendors_listing(): void
    {
        ['token' => $tokenA] = $this->registerVendor('agro', 'owner@example.test');
        ['token' => $tokenB] = $this->registerVendor('agro', 'other@example.test');

        $product = Product::query()->create([
            'vendor_id' => Vendor::query()->firstOrFail()->id,
            'category' => 'agro',
            'title' => 'Owner produce',
            'status' => 'active',
        ]);

        $forbidden = $this->withToken($tokenB)->putJson("/api/v1/listings/{$product->id}", [
            'title' => 'Hijacked title',
        ]);

        $forbidden->assertForbidden();

        // Sanctum memoizes the resolved user per guard instance; reset it so
        // the next request in this test resolves its own token.
        $this->app->make('auth')->forgetGuards();

        $allowed = $this->withToken($tokenA)->putJson("/api/v1/listings/{$product->id}", [
            'title' => 'Renamed produce',
        ]);

        $allowed->assertOk()->assertJsonPath('data.title', 'Renamed produce');
    }

    public function test_deleting_a_listing_archives_it_instead_of_removing_it(): void
    {
        ['token' => $token] = $this->registerVendor();

        $product = Product::query()->create([
            'vendor_id' => Vendor::query()->firstOrFail()->id,
            'category' => 'agro',
            'title' => 'Archive me',
            'status' => 'active',
        ]);

        $this->withToken($token)->deleteJson("/api/v1/listings/{$product->id}")
            ->assertOk();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'archived']);
        $this->assertDatabaseMissing('products', ['id' => $product->id, 'status' => 'active']);
    }

    public function test_a_driver_cannot_create_listings(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'Rider Ram',
            'email' => 'ram@example.test',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'accept_terms' => true,
            'role' => 'driver',
        ]);

        $register->assertCreated();

        $this->withToken((string) $register->json('token'))
            ->postJson('/api/v1/listings', [
                'title' => 'Not a vendor',
                'category' => 'agro',
            ])
            ->assertForbidden();
    }

    public function test_a_locality_must_belong_to_the_selected_district(): void
    {
        ['token' => $token] = $this->registerVendor();

        $district = District::query()->create(['name' => 'Test District']);
        $other = District::query()->create(['name' => 'Other District']);
        $locality = Locality::query()->create(['district_id' => $other->id, 'name' => 'Centre']);

        $this->withToken($token)->postJson('/api/v1/listings', [
            'title' => 'Wrong locality pairing',
            'category' => 'agro',
            'district_id' => $district->id,
            'locality_id' => $locality->id,
            'status' => 'active',
        ])->assertUnprocessable();
    }
}
