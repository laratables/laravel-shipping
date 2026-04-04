<?php

namespace Laratables\Shipping\Tests\Feature;

use Laratables\Shipping\Models\ShippingWeightBand;
use Laratables\Shipping\Services\ShippingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laratables\Shipping\Tests\TestCase;

class ShippingWeightBandFeatureTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createBand(array $attributes = []): ShippingWeightBand
    {
        return ShippingWeightBand::create(array_merge([
            'max_kg'      => 5.0,
            'rate_per_kg' => 2.50,
            'label'       => 'Test band',
            'sort_order'  => 1,
            'active'      => true,
        ], $attributes));
    }

    private function seedDefaultBands(): void
    {
        ShippingWeightBand::insert([
            ['max_kg' => 1.0,        'rate_per_kg' => 3.00, 'label' => 'Up to 1 kg',  'sort_order' => 1, 'active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['max_kg' => 5.0,        'rate_per_kg' => 2.50, 'label' => '1 – 5 kg',    'sort_order' => 2, 'active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['max_kg' => 15.0,       'rate_per_kg' => 2.00, 'label' => '5 – 15 kg',   'sort_order' => 3, 'active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['max_kg' => 30.0,       'rate_per_kg' => 1.75, 'label' => '15 – 30 kg',  'sort_order' => 4, 'active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['max_kg' => 999999.999, 'rate_per_kg' => 1.50, 'label' => '30 kg+',      'sort_order' => 5, 'active' => true,  'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function singleItem(float $weightKg = 1.0, int $quantity = 1): array
    {
        return [[
            'product_id' => 1,
            'name'       => 'Test Product',
            'weight_kg'  => $weightKg,
            'quantity'   => $quantity,
        ]];
    }

    private function calculatorFromDb(): ShippingCalculator
    {
        return (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withWeightBands(ShippingWeightBand::toCalculatorBands());
    }

    // =========================================================================
    // ShippingWeightBand::toCalculatorBands()
    // =========================================================================

    public function test_to_calculator_bands_returns_array()
    {
        $this->seedDefaultBands();

        $bands = ShippingWeightBand::toCalculatorBands();

        $this->assertIsArray($bands);
    }

    public function test_to_calculator_bands_returns_correct_count()
    {
        $this->seedDefaultBands();

        $bands = ShippingWeightBand::toCalculatorBands();

        $this->assertCount(5, $bands);
    }

    public function test_to_calculator_bands_each_entry_has_max_kg_and_rate_per_kg()
    {
        $this->seedDefaultBands();

        foreach (ShippingWeightBand::toCalculatorBands() as $band) {
            $this->assertArrayHasKey('max_kg', $band);
            $this->assertArrayHasKey('rate_per_kg', $band);
        }
    }

    public function test_to_calculator_bands_values_are_floats()
    {
        $this->seedDefaultBands();

        foreach (ShippingWeightBand::toCalculatorBands() as $band) {
            $this->assertIsFloat($band['max_kg']);
            $this->assertIsFloat($band['rate_per_kg']);
        }
    }

    public function test_to_calculator_bands_returns_correct_rates()
    {
        $this->seedDefaultBands();

        $bands = ShippingWeightBand::toCalculatorBands();

        $this->assertEquals(3.00, $bands[0]['rate_per_kg']);
        $this->assertEquals(2.50, $bands[1]['rate_per_kg']);
        $this->assertEquals(2.00, $bands[2]['rate_per_kg']);
        $this->assertEquals(1.75, $bands[3]['rate_per_kg']);
        $this->assertEquals(1.50, $bands[4]['rate_per_kg']);
    }

    public function test_to_calculator_bands_returns_empty_array_when_no_bands_exist()
    {
        $bands = ShippingWeightBand::toCalculatorBands();

        $this->assertEmpty($bands);
    }

    // =========================================================================
    // Active scope
    // =========================================================================

    public function test_inactive_bands_are_excluded_from_to_calculator_bands()
    {
        $this->createBand(['max_kg' => 5.0,  'rate_per_kg' => 2.50, 'active' => true]);
        $this->createBand(['max_kg' => 15.0, 'rate_per_kg' => 2.00, 'active' => false]);
        $this->createBand(['max_kg' => 30.0, 'rate_per_kg' => 1.75, 'active' => true]);

        $bands = ShippingWeightBand::toCalculatorBands();

        $this->assertCount(2, $bands);
    }

    public function test_all_inactive_bands_returns_empty_array()
    {
        $this->createBand(['active' => false]);
        $this->createBand(['active' => false]);

        $bands = ShippingWeightBand::toCalculatorBands();

        $this->assertEmpty($bands);
    }

    public function test_toggling_band_to_inactive_removes_it_from_results()
    {
        $band = $this->createBand(['active' => true]);

        $this->assertCount(1, ShippingWeightBand::toCalculatorBands());

        $band->update(['active' => false]);

        $this->assertCount(0, ShippingWeightBand::toCalculatorBands());
    }

    public function test_toggling_band_back_to_active_restores_it_in_results()
    {
        $band = $this->createBand(['active' => false]);

        $this->assertCount(0, ShippingWeightBand::toCalculatorBands());

        $band->update(['active' => true]);

        $this->assertCount(1, ShippingWeightBand::toCalculatorBands());
    }

    // =========================================================================
    // Ordering
    // =========================================================================

    public function test_bands_are_ordered_by_max_kg_ascending()
    {
        // Insert in reverse order to confirm ordering is by max_kg, not insert order
        $this->createBand(['max_kg' => 30.0,  'rate_per_kg' => 1.75]);
        $this->createBand(['max_kg' => 1.0,   'rate_per_kg' => 3.00]);
        $this->createBand(['max_kg' => 15.0,  'rate_per_kg' => 2.00]);
        $this->createBand(['max_kg' => 5.0,   'rate_per_kg' => 2.50]);

        $bands = ShippingWeightBand::toCalculatorBands();

        $this->assertEquals(1.0,  $bands[0]['max_kg']);
        $this->assertEquals(5.0,  $bands[1]['max_kg']);
        $this->assertEquals(15.0, $bands[2]['max_kg']);
        $this->assertEquals(30.0, $bands[3]['max_kg']);
    }

    public function test_bands_ordered_correctly_regardless_of_sort_order_column()
    {
        // sort_order column is cosmetic — max_kg drives calculator ordering
        $this->createBand(['max_kg' => 5.0,  'rate_per_kg' => 2.50, 'sort_order' => 99]);
        $this->createBand(['max_kg' => 1.0,  'rate_per_kg' => 3.00, 'sort_order' => 1]);

        $bands = ShippingWeightBand::toCalculatorBands();

        $this->assertEquals(1.0, $bands[0]['max_kg']);
        $this->assertEquals(5.0, $bands[1]['max_kg']);
    }

    // =========================================================================
    // End-to-end: DB bands → ShippingCalculator
    // =========================================================================

    public function test_calculator_uses_first_band_from_db_for_light_order()
    {
        $this->seedDefaultBands();

        // 0.5 kg × £3.00 + £2.50 base = £4.00
        $result = $this->calculatorFromDb()->calculate($this->singleItem(0.5, 1));

        $this->assertEquals(4.00, $result['total_cost']);
    }

    public function test_calculator_uses_second_band_from_db_for_mid_weight_order()
    {
        $this->seedDefaultBands();

        // 3.0 kg × £2.50 + £2.50 base = £10.00
        $result = $this->calculatorFromDb()->calculate($this->singleItem(3.0, 1));

        $this->assertEquals(10.00, $result['total_cost']);
    }

    public function test_calculator_uses_correct_band_after_band_rate_updated_in_db()
    {
        $this->seedDefaultBands();

        // Confirm original rate
        $before = $this->calculatorFromDb()->calculate($this->singleItem(0.5, 1));
        $this->assertEquals(4.00, $before['total_cost']);

        // Update the first band rate in the DB
        ShippingWeightBand::where('max_kg', 1.0)->update(['rate_per_kg' => 5.00]);

        // Re-resolve bands from DB — 0.5 kg × £5.00 + £2.50 base = £5.00
        $after = $this->calculatorFromDb()->calculate($this->singleItem(0.5, 1));
        $this->assertEquals(5.00, $after['total_cost']);
    }

    public function test_calculator_falls_into_next_band_when_one_band_deactivated()
    {
        $this->seedDefaultBands();

        // Deactivate the first band (≤1 kg at £3.00/kg)
        ShippingWeightBand::where('max_kg', 1.0)->update(['active' => false]);

        // 0.5 kg now falls into the second band (≤5 kg at £2.50/kg)
        // 0.5 × £2.50 + £2.50 base = £3.75
        $result = $this->calculatorFromDb()->calculate($this->singleItem(0.5, 1));

        $this->assertEquals(3.75, $result['total_cost']);
    }

    public function test_calculator_uses_newly_inserted_band_from_db()
    {
        $this->seedDefaultBands();

        // Insert a new band between 5–15 kg at a different rate
        ShippingWeightBand::where('max_kg', 15.0)->update(['active' => false]);
        ShippingWeightBand::create([
            'max_kg'      => 15.0,
            'rate_per_kg' => 3.50,
            'label'       => 'New custom band',
            'sort_order'  => 3,
            'active'      => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // 10.0 kg × £3.50 + £2.50 base = £37.50
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withHeavyItemThreshold(15.0, 3.00)
            ->withWeightBands(ShippingWeightBand::toCalculatorBands())
            ->calculate($this->singleItem(10.0, 1));

        $this->assertEquals(37.50, $result['total_cost']);
    }

    public function test_combined_weight_across_multiple_products_uses_correct_db_band()
    {
        $this->seedDefaultBands();

        // Two products: 1.5 kg + 1.5 kg = 3.0 kg combined → second band £2.50/kg
        // 3.0 × £2.50 = £7.50 + £2.50 base + £1.50 multi surcharge = £11.50
        $cart = [
            ['product_id' => 1, 'name' => 'A', 'weight_kg' => 1.5, 'quantity' => 1],
            ['product_id' => 2, 'name' => 'B', 'weight_kg' => 1.5, 'quantity' => 1],
        ];

        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withWeightBands(ShippingWeightBand::toCalculatorBands())
            ->calculate($cart);

        $this->assertEquals(11.50, $result['total_cost']);
    }

    // =========================================================================
    // Service container resolution
    // =========================================================================

    public function test_shipping_calculator_resolves_from_container()
    {
        $this->seedDefaultBands();

        $calculator = $this->app->make(ShippingCalculator::class);

        $this->assertInstanceOf(ShippingCalculator::class, $calculator);
    }

    public function test_container_resolves_same_singleton_instance_twice()
    {
        $this->seedDefaultBands();

        $first  = $this->app->make(ShippingCalculator::class);
        $second = $this->app->make(ShippingCalculator::class);

        $this->assertSame($first, $second);
    }

    public function test_calculator_resolved_from_container_produces_correct_cost()
    {
        $this->seedDefaultBands();

        // Force a fresh singleton resolution with DB bands loaded
        $this->app->forgetInstance(ShippingCalculator::class);

        config([
            'shipping.enabled'                => true,
            'shipping.base_fee'               => 2.50,
            'shipping.multi_product_surcharge'=> 1.50,
            'shipping.heavy_item_threshold_kg'=> 10.0,
            'shipping.heavy_item_surcharge'   => 3.00,
            'shipping.max_weight_kg'          => 100.0,
            'shipping.free_enabled'           => false,
        ]);

        $calculator = $this->app->make(ShippingCalculator::class);

        // 0.5 kg × £3.00 + £2.50 base = £4.00
        $result = $calculator->calculate($this->singleItem(0.5, 1));

        $this->assertEquals(4.00, $result['total_cost']);
    }

    // =========================================================================
    // Cache
    // =========================================================================

    public function test_bands_are_cached_after_first_resolution()
    {
        $this->seedDefaultBands();

        Cache::tags('shipping')->flush();

        $this->assertFalse(Cache::tags('shipping')->has('shipping_weight_bands'));

        ShippingWeightBand::toCalculatorBands();

        // Manually cache as the provider would
        Cache::tags('shipping')->remember(
            'shipping_weight_bands',
            now()->addHour(),
            fn () => ShippingWeightBand::toCalculatorBands()
        );

        $this->assertTrue(Cache::tags('shipping')->has('shipping_weight_bands'));
    }

    public function test_flushing_shipping_cache_tag_clears_weight_bands()
    {
        $this->seedDefaultBands();

        Cache::tags('shipping')->remember(
            'shipping_weight_bands',
            now()->addHour(),
            fn () => ShippingWeightBand::toCalculatorBands()
        );

        $this->assertTrue(Cache::tags('shipping')->has('shipping_weight_bands'));

        Cache::tags('shipping')->flush();

        $this->assertFalse(Cache::tags('shipping')->has('shipping_weight_bands'));
    }

    public function test_stale_cache_is_bypassed_after_flush_and_new_bands_are_loaded()
    {
        $this->seedDefaultBands();

        // Prime the cache
        $cached = Cache::tags('shipping')->remember(
            'shipping_weight_bands',
            now()->addHour(),
            fn () => ShippingWeightBand::toCalculatorBands()
        );

        $this->assertEquals(3.00, $cached[0]['rate_per_kg']);

        // Update the DB and flush cache
        ShippingWeightBand::where('max_kg', 1.0)->update(['rate_per_kg' => 9.99]);
        Cache::tags('shipping')->flush();

        // Re-load — should reflect the DB change
        $fresh = Cache::tags('shipping')->remember(
            'shipping_weight_bands',
            now()->addHour(),
            fn () => ShippingWeightBand::toCalculatorBands()
        );

        $this->assertEquals(9.99, $fresh[0]['rate_per_kg']);
    }

    // =========================================================================
    // Disabled calculator skips DB
    // =========================================================================

    public function test_disabled_calculator_returns_free_shipping_without_db_bands()
    {
        // No bands seeded — disabled mode should not need them
        config(['shipping.enabled' => false, 'shipping.disabled_fallback.mode' => 'free']);

        $this->app->forgetInstance(ShippingCalculator::class);

        $calculator = $this->app->make(ShippingCalculator::class);
        $result     = $calculator->calculate($this->singleItem());

        $this->assertFalse($result['calculator_enabled']);
        $this->assertEquals(0.00, $result['total_cost']);
    }

    public function test_disabled_calculator_returns_flat_rate_without_db_bands()
    {
        config([
            'shipping.enabled'                        => false,
            'shipping.disabled_fallback.mode'         => 'flat_rate',
            'shipping.disabled_fallback.flat_rate_amount' => 4.99,
        ]);

        $this->app->forgetInstance(ShippingCalculator::class);

        $calculator = $this->app->make(ShippingCalculator::class);
        $result     = $calculator->calculate($this->singleItem());

        $this->assertFalse($result['calculator_enabled']);
        $this->assertEquals(4.99, $result['total_cost']);
    }
}
