<?php

namespace Laratables\Shipping\Tests\Unit;

use Laratables\Shipping\Services\ShippingCalculator;
use Laratables\Shipping\Services\ShippingResolver;
use PHPUnit\Framework\TestCase;

class ShippingResolverTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function resolver(?ShippingCalculator $calculator = null): ShippingResolver
    {
        return new ShippingResolver(
            $calculator ?? $this->calculator()
        );
    }

    private function calculator(): ShippingCalculator
    {
        return (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withBaseFee(2.50)
            ->withMultiProductSurcharge(1.50)
            ->withHeavyItemThreshold(10.0, 3.00)
            ->withMaxShippableWeight(100.0)
            ->withWeightBands([
                ['max_kg' => 1.0,           'rate_per_kg' => 3.00],
                ['max_kg' => 5.0,           'rate_per_kg' => 2.50],
                ['max_kg' => PHP_FLOAT_MAX, 'rate_per_kg' => 2.00],
            ]);
    }

    private function weightedItem(float $weightKg = 0.5, int $quantity = 1, ?float $shippingCost = null): array
    {
        return [
            'product_id'    => 1,
            'name'          => 'Weighted Product',
            'weight_kg'     => $weightKg,
            'shipping_cost' => $shippingCost,
            'quantity'      => $quantity,
            'price'         => 10.00,
        ];
    }

    private function flatItem(float $shippingCost = 3.99, int $quantity = 1): array
    {
        return [
            'product_id'    => 2,
            'name'          => 'Flat Rate Product',
            'weight_kg'     => null,
            'shipping_cost' => $shippingCost,
            'quantity'      => $quantity,
            'price'         => 20.00,
        ];
    }

    private function digitalItem(): array
    {
        return [
            'product_id'    => 3,
            'name'          => 'Event Ticket',
            'weight_kg'     => null,
            'shipping_cost' => null,
            'quantity'      => 2,
            'price'         => 50.00,
        ];
    }

    // =========================================================================
    // Result structure
    // =========================================================================

    public function test_result_contains_expected_keys()
    {
        $result = $this->resolver()->resolve([$this->weightedItem()]);

        $this->assertArrayHasKey('enabled', $result);
        $this->assertArrayHasKey('total_shipping', $result);
        $this->assertArrayHasKey('algorithm_total', $result);
        $this->assertArrayHasKey('flat_total', $result);
        $this->assertArrayHasKey('is_free_shipping', $result);
        $this->assertArrayHasKey('free_shipping_info', $result);
        $this->assertArrayHasKey('algorithm_result', $result);
        $this->assertArrayHasKey('flat_items', $result);
        $this->assertArrayHasKey('weighted_item_count', $result);
        $this->assertArrayHasKey('flat_item_count', $result);
        $this->assertArrayHasKey('has_shippable_items', $result);
        $this->assertArrayHasKey('warnings', $result);
    }

    // =========================================================================
    // Routing scenario 1 — weight_kg not null → algorithm pool
    // =========================================================================

    public function test_item_with_weight_kg_routes_to_algorithm_pool()
    {
        $result = $this->resolver()->resolve([$this->weightedItem(0.5, 1)]);

        $this->assertEquals(1, $result['weighted_item_count']);
        $this->assertEquals(0, $result['flat_item_count']);
    }

    public function test_item_with_weight_kg_produces_algorithm_result()
    {
        $result = $this->resolver()->resolve([$this->weightedItem(0.5, 1)]);

        $this->assertNotNull($result['algorithm_result']);
        $this->assertGreaterThan(0, $result['algorithm_total']);
    }

    public function test_item_with_weight_kg_calculates_correct_total()
    {
        // 0.5 kg × £3.00/kg + £2.50 base = £4.00
        $result = $this->resolver()->resolve([$this->weightedItem(0.5, 1)]);

        $this->assertEquals(4.00, $result['total_shipping']);
    }

    public function test_item_with_weight_kg_and_shipping_cost_uses_algorithm_not_flat_rate()
    {
        // Both weight_kg and shipping_cost set — weight_kg must win
        $item   = $this->weightedItem(0.5, 1, shippingCost: 3.99);
        $result = $this->resolver()->resolve([$item]);

        // Algorithm: 0.5 kg × £3.00 + £2.50 base = £4.00 — NOT £3.99
        $this->assertEquals(4.00, $result['total_shipping']);
        $this->assertEquals(1, $result['weighted_item_count']);
        $this->assertEquals(0, $result['flat_item_count']);
    }

    public function test_shipping_cost_is_ignored_when_weight_kg_is_present()
    {
        $item   = $this->weightedItem(0.5, 1, shippingCost: 99.99);
        $result = $this->resolver()->resolve([$item]);

        // shipping_cost of £99.99 must never appear in the total
        $this->assertNotEquals(99.99, $result['total_shipping']);
        $this->assertEquals(0.00, $result['flat_total']);
    }

    public function test_algorithm_total_is_set_and_flat_total_is_zero_for_weighted_only_cart()
    {
        $result = $this->resolver()->resolve([$this->weightedItem(0.5, 1)]);

        $this->assertGreaterThan(0, $result['algorithm_total']);
        $this->assertEquals(0.00, $result['flat_total']);
    }

    // =========================================================================
    // Routing scenario 2 — weight_kg null, shipping_cost not null → flat pool
    // =========================================================================

    public function test_item_with_null_weight_kg_and_shipping_cost_routes_to_flat_pool()
    {
        $result = $this->resolver()->resolve([$this->flatItem(3.99)]);

        $this->assertEquals(0, $result['weighted_item_count']);
        $this->assertEquals(1, $result['flat_item_count']);
    }

    public function test_flat_rate_item_uses_shipping_cost_directly()
    {
        $result = $this->resolver()->resolve([$this->flatItem(3.99)]);

        $this->assertEquals(3.99, $result['total_shipping']);
        $this->assertEquals(3.99, $result['flat_total']);
    }

    public function test_flat_rate_item_multiplies_shipping_cost_by_quantity()
    {
        $result = $this->resolver()->resolve([$this->flatItem(3.99, 2)]);

        $this->assertEquals(7.98, $result['total_shipping']);
    }

    public function test_flat_rate_item_appears_in_flat_items_array()
    {
        $result = $this->resolver()->resolve([$this->flatItem(3.99)]);

        $this->assertNotEmpty($result['flat_items']);
        $this->assertEquals('Flat Rate Product', $result['flat_items'][0]['name']);
        $this->assertEquals(3.99, $result['flat_items'][0]['shipping_cost']);
        $this->assertEquals(3.99, $result['flat_items'][0]['line_total']);
    }

    public function test_flat_rate_algorithm_total_is_zero_and_flat_total_is_set()
    {
        $result = $this->resolver()->resolve([$this->flatItem(3.99)]);

        $this->assertEquals(0.00, $result['algorithm_total']);
        $this->assertEquals(3.99, $result['flat_total']);
        $this->assertNull($result['algorithm_result']);
    }

    public function test_multiple_flat_rate_items_are_summed()
    {
        $cart = [
            $this->flatItem(3.99),
            [
                'product_id'    => 4,
                'name'          => 'Another Flat Product',
                'weight_kg'     => null,
                'shipping_cost' => 2.50,
                'quantity'      => 1,
                'price'         => 15.00,
            ],
        ];

        $result = $this->resolver()->resolve($cart);

        $this->assertEquals(6.49, $result['total_shipping']);
        $this->assertEquals(2, $result['flat_item_count']);
    }

    // =========================================================================
    // Routing scenario 3 — both null → skipped (not shippable)
    // =========================================================================

    public function test_item_with_both_null_is_skipped()
    {
        $result = $this->resolver()->resolve([$this->digitalItem()]);

        $this->assertEquals(0, $result['weighted_item_count']);
        $this->assertEquals(0, $result['flat_item_count']);
    }

    public function test_item_with_both_null_returns_zero_shipping()
    {
        $result = $this->resolver()->resolve([$this->digitalItem()]);

        $this->assertEquals(0.00, $result['total_shipping']);
    }

    public function test_item_with_both_null_returns_has_shippable_items_false()
    {
        $result = $this->resolver()->resolve([$this->digitalItem()]);

        $this->assertFalse($result['has_shippable_items']);
    }

    public function test_digital_items_do_not_affect_shipping_total_in_mixed_cart()
    {
        $cart = [
            $this->digitalItem(),  // skipped
            $this->flatItem(3.99), // flat rate
        ];

        $result = $this->resolver()->resolve($cart);

        // Only the flat rate item should contribute
        $this->assertEquals(3.99, $result['total_shipping']);
        $this->assertEquals(1, $result['flat_item_count']);
        $this->assertEquals(0, $result['weighted_item_count']);
    }

    public function test_cart_of_only_digital_items_returns_empty_result()
    {
        $cart = [
            $this->digitalItem(),
            $this->digitalItem(),
        ];

        $result = $this->resolver()->resolve($cart);

        $this->assertEquals(0.00, $result['total_shipping']);
        $this->assertFalse($result['has_shippable_items']);
        $this->assertNull($result['algorithm_result']);
        $this->assertEmpty($result['flat_items']);
    }

    // =========================================================================
    // Priority rule — weight_kg takes precedence over shipping_cost
    // =========================================================================

    public function test_weight_kg_takes_priority_over_shipping_cost_on_same_product()
    {
        // Explicitly confirm the priority rule with a direct comparison
        $withBoth    = $this->weightedItem(0.5, 1, shippingCost: 99.00);
        $withWeight  = $this->weightedItem(0.5, 1, shippingCost: null);

        $resultBoth   = $this->resolver()->resolve([$withBoth]);
        $resultWeight = $this->resolver()->resolve([$withWeight]);

        // Both should produce identical totals — shipping_cost is ignored in both cases
        $this->assertEquals($resultWeight['total_shipping'], $resultBoth['total_shipping']);
    }

    public function test_priority_rule_applied_independently_per_item_in_mixed_cart()
    {
        $cart = [
            $this->weightedItem(0.5, 1, shippingCost: 99.00), // weight wins — £4.00
            $this->flatItem(3.99),                             // flat rate — £3.99
            $this->digitalItem(),                              // skipped
        ];

        $result = $this->resolver()->resolve($cart);

        $this->assertEquals(1, $result['weighted_item_count']);
        $this->assertEquals(1, $result['flat_item_count']);
        $this->assertEquals(4.00, $result['algorithm_total']);
        $this->assertEquals(3.99, $result['flat_total']);
        $this->assertEquals(7.99, $result['total_shipping']);
    }

    // =========================================================================
    // Mixed cart — weighted + flat combined
    // =========================================================================

    public function test_mixed_cart_combines_algorithm_and_flat_totals()
    {
        $cart = [
            $this->weightedItem(0.5, 1), // 0.5 kg × £3.00 + £2.50 base = £4.00
            $this->flatItem(3.99),        // flat £3.99
        ];

        $result = $this->resolver()->resolve($cart);

        $this->assertEquals(4.00, $result['algorithm_total']);
        $this->assertEquals(3.99, $result['flat_total']);
        $this->assertEquals(7.99, $result['total_shipping']);
    }

    public function test_mixed_cart_has_shippable_items_true()
    {
        $cart = [
            $this->weightedItem(),
            $this->flatItem(),
        ];

        $result = $this->resolver()->resolve($cart);

        $this->assertTrue($result['has_shippable_items']);
    }

    public function test_mixed_cart_total_shipping_is_rounded_to_two_decimal_places()
    {
        $cart = [
            $this->weightedItem(0.333, 1),
            $this->flatItem(1.005),
        ];

        $result = $this->resolver()->resolve($cart);

        $this->assertEquals(
            round($result['total_shipping'], 2),
            $result['total_shipping']
        );
    }

    // =========================================================================
    // Empty cart
    // =========================================================================

    public function test_empty_cart_returns_zero_shipping()
    {
        $result = $this->resolver()->resolve([]);

        $this->assertEquals(0.00, $result['total_shipping']);
    }

    public function test_empty_cart_returns_has_shippable_items_false()
    {
        $result = $this->resolver()->resolve([]);

        $this->assertFalse($result['has_shippable_items']);
    }

    public function test_empty_cart_returns_null_algorithm_result()
    {
        $result = $this->resolver()->resolve([]);

        $this->assertNull($result['algorithm_result']);
    }

    // =========================================================================
    // Disabled calculator — free mode
    // =========================================================================

    public function test_disabled_free_mode_returns_zero_shipping()
    {
        $calculator = (new ShippingCalculator())->disable('free', message: 'Free shipping weekend!');
        $result     = $this->resolver($calculator)->resolve([$this->weightedItem()]);

        $this->assertEquals(0.00, $result['total_shipping']);
        $this->assertFalse($result['enabled']);
    }

    public function test_disabled_free_mode_returns_is_free_shipping_true()
    {
        $calculator = (new ShippingCalculator())->disable('free');
        $result     = $this->resolver($calculator)->resolve([$this->weightedItem()]);

        $this->assertTrue($result['is_free_shipping']);
    }

    // =========================================================================
    // Disabled calculator — flat rate mode
    // =========================================================================

    public function test_disabled_flat_rate_mode_returns_configured_amount()
    {
        $calculator = (new ShippingCalculator())->disable('flat_rate', flatRate: 5.99);
        $result     = $this->resolver($calculator)->resolve([$this->weightedItem()]);

        $this->assertEquals(5.99, $result['total_shipping']);
        $this->assertFalse($result['enabled']);
    }

    public function test_disabled_flat_rate_mode_ignores_cart_contents()
    {
        $calculator = (new ShippingCalculator())->disable('flat_rate', flatRate: 5.99);

        $singleItem = $this->resolver($calculator)->resolve([$this->weightedItem()]);
        $multiItem  = $this->resolver($calculator)->resolve([
            $this->weightedItem(),
            $this->flatItem(),
            $this->digitalItem(),
        ]);

        // Same flat rate regardless of cart contents
        $this->assertEquals($singleItem['total_shipping'], $multiItem['total_shipping']);
    }

    // =========================================================================
    // Disabled calculator — unavailable mode
    // =========================================================================

    public function test_disabled_unavailable_mode_returns_warning_not_exception()
    {
        $calculator = (new ShippingCalculator())->disable('unavailable', message: 'No shipping available.');
        $result     = $this->resolver($calculator)->resolve([$this->weightedItem()]);

        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('No shipping available.', $result['warnings'][0]);
    }

    public function test_disabled_unavailable_mode_returns_zero_shipping()
    {
        $calculator = (new ShippingCalculator())->disable('unavailable', message: 'No shipping available.');
        $result     = $this->resolver($calculator)->resolve([$this->weightedItem()]);

        $this->assertEquals(0.00, $result['total_shipping']);
        $this->assertFalse($result['enabled']);
    }

    // =========================================================================
    // Disabled calculator — empty cart still returns empty not disabled result
    // =========================================================================

    public function test_empty_cart_returns_zero_even_when_calculator_disabled()
    {
        $calculator = (new ShippingCalculator())->disable('flat_rate', flatRate: 5.99);
        $result     = $this->resolver($calculator)->resolve([]);

        // Empty cart guard fires before disabled check — no flat rate on empty cart
        $this->assertEquals(0.00, $result['total_shipping']);
        $this->assertFalse($result['has_shippable_items']);
    }

    // =========================================================================
    // Free shipping info
    // =========================================================================

    public function test_free_shipping_info_returns_correct_progress()
    {
        $calculator = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withFreeShipping(75.00);

        $info = $this->resolver($calculator)->freeShippingInfo(50.00);

        $this->assertFalse($info['is_free']);
        $this->assertEquals(25.00, $info['remaining']);
        $this->assertEqualsWithDelta(66.7, $info['progress_pct'], 0.1);
    }

    public function test_free_shipping_applied_when_subtotal_meets_threshold()
    {
        $calculator = (new ShippingCalculator())
            ->withFreeShipping(75.00)
            ->withWeightBands([['max_kg' => PHP_FLOAT_MAX, 'rate_per_kg' => 3.00]]);

        $result = $this->resolver($calculator)->resolve(
            [$this->weightedItem(0.5, 1)],
            orderSubtotal: 80.00
        );

        $this->assertTrue($result['is_free_shipping']);
        $this->assertEquals(0.00, $result['total_shipping']);
    }

    // =========================================================================
    // Zero weight_kg treated as not weighted
    // =========================================================================

    public function test_item_with_zero_weight_kg_falls_back_to_flat_rate()
    {
        $item = [
            'product_id'    => 1,
            'name'          => 'Zero Weight Product',
            'weight_kg'     => 0.0,
            'shipping_cost' => 2.99,
            'quantity'      => 1,
            'price'         => 10.00,
        ];

        $result = $this->resolver()->resolve([$item]);

        // weight_kg = 0 is treated as not set — flat rate used instead
        $this->assertEquals(2.99, $result['total_shipping']);
        $this->assertEquals(1, $result['flat_item_count']);
        $this->assertEquals(0, $result['weighted_item_count']);
    }
}
