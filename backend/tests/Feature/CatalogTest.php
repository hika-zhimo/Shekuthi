<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VerificationFeeSetting;
use App\Support\BlindIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_batches_badges_and_reads_current_fee_once_per_request(): void
    {
        $vendor = $this->makeVendor('Query Budget');
        $fee = VerificationFeeSetting::current();
        $fee->update(['amount_inr' => 150]);
        $verified = $this->makeProduct($vendor, ['title' => 'Verified produce']);
        Badge::query()->create([
            'subject_type' => Product::class,
            'subject_id' => $verified->id,
            'volunteer_name' => 'Test Volunteer',
            'issued_at' => now(),
        ]);
        $revoked = $this->makeProduct($vendor, ['title' => 'Revoked produce']);
        Badge::query()->create([
            'subject_type' => Product::class,
            'subject_id' => $revoked->id,
            'volunteer_name' => 'Test Volunteer',
            'issued_at' => now(),
            'revoked_at' => now(),
        ]);
        for ($i = 0; $i < 10; $i++) {
            $this->makeProduct($vendor, ['title' => 'Test batch '.$i]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->getJson('/api/v1/catalog')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();
        $this->assertCount(1, $queries->filter(fn ($sql) => str_contains($sql, 'from "badges"')));
        $this->assertCount(1, $queries->filter(fn ($sql) => str_contains($sql, 'from "verification_fee_settings"')));
        $items = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($items[$verified->id]['is_verified']);
        $this->assertSame('Test Volunteer', $items[$verified->id]['verified_by']);
        $this->assertArrayNotHasKey('verification_fee_inr', $items[$verified->id]);
        $this->assertFalse($items[$revoked->id]['is_verified']);
        $this->assertEquals(150, $items[$revoked->id]['verification_fee_inr']);

        $fee->update(['amount_inr' => 200]);
        $this->getJson('/api/v1/catalog/'.$revoked->id)
            ->assertOk()->assertJsonPath('data.verification_fee_inr', 200);
        $fee->update(['amount_inr' => null]);
        $this->getJson('/api/v1/catalog/'.$revoked->id)
            ->assertOk()->assertJsonMissingPath('data.verification_fee_inr');
    }

    private function makeVendor(string $name, string $category = 'agro'): Vendor
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email = strtolower(str_replace(' ', '.', $name)).'@example.test',
            'email_index' => BlindIndex::make($email),
            'password' => 'secret1234',
            'role' => 'vendor',
        ]);

        return Vendor::query()->create([
            'user_id' => $user->id,
            'display_name' => $name.' Farm',
            'category' => $category,
        ]);
    }

    private function makeProduct(Vendor $vendor, array $overrides = []): Product
    {
        return Product::query()->create($overrides + [
            'vendor_id' => $vendor->id,
            'category' => 'agro',
            'title' => 'Tomatoes',
            'description' => 'Red and ripe.',
            'price' => 20,
            'status' => 'active',
        ]);
    }

    public function test_guests_can_browse_the_catalog_without_an_account(): void
    {
        $vendor = $this->makeVendor('Farm One');
        $this->makeProduct($vendor, ['title' => 'Fresh tomatoes']);

        // Website: no auth, works.
        $this->get('/catalog')
            ->assertOk()
            ->assertSee('Fresh tomatoes');

        // API: no token, works.
        $this->getJson('/api/v1/catalog')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Fresh tomatoes');
    }

    public function test_draft_and_archived_listings_are_hidden_from_the_catalog(): void
    {
        $vendor = $this->makeVendor('Farm Two');

        $this->makeProduct($vendor, ['title' => 'Visible item', 'status' => 'active']);
        $this->makeProduct($vendor, ['title' => 'Draft item', 'status' => 'draft']);
        $this->makeProduct($vendor, ['title' => 'Archived item', 'status' => 'archived']);

        $this->get('/catalog')->assertOk()->assertSee('Visible item')->assertDontSee('Draft item');

        $this->getJson('/api/v1/catalog')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_search_and_category_filters_narrow_results(): void
    {
        $vendor = $this->makeVendor('Farm Three');

        $this->makeProduct($vendor, ['title' => 'Tomato basket', 'category' => 'agro']);
        $this->makeProduct($vendor, ['title' => 'Cotton hammock', 'category' => 'traditional']);
        $this->makeProduct($vendor, ['title' => 'Mountain cabin', 'category' => 'rental_homestay', 'price' => 900]);

        $this->getJson('/api/v1/catalog?q=tomato')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Tomato basket');

        $this->getJson('/api/v1/catalog?category=rental_homestay')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Mountain cabin');

        // Same filters on the website.
        $this->get('/catalog?q=tomato')->assertOk()->assertSee('Tomato basket')->assertDontSee('Cotton hammock');
    }

    public function test_the_catalog_api_rejects_invalid_filters(): void
    {
        $this->getJson('/api/v1/catalog?category=nonsense')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');
    }

    public function test_vendor_page_lists_only_active_products_and_marks_verification(): void
    {
        $vendor = $this->makeVendor('Farm Four');
        $this->makeProduct($vendor, ['title' => 'Listed honey', 'category' => 'traditional']);
        $this->makeProduct($vendor, ['title' => 'Hidden honey', 'status' => 'inactive']);

        $response = $this->getJson("/api/v1/vendors/{$vendor->id}");

        $response->assertOk()
            ->assertJsonPath('data.display_name', 'Farm Four Farm')
            ->assertJsonPath('data.is_verified', false);

        $titles = collect($response->json('data.listings'))->pluck('title')->all();
        $this->assertContains('Listed honey', $titles);
        $this->assertNotContains('Hidden honey', $titles);
    }
}
