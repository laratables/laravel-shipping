<?php

namespace Laratables\Shipping\Tests\Unit;

use Laratables\Shipping\Services\ShippingCalculator;
use PHPUnit\Framework\TestCase;

class ShippingCalculatorTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function calculator(): ShippingCalculator
    {
        return (new ShippingCalculator())
            ->withFreeShipping(75.00);
    }

    private function singleItem(float $weightKg = 0.5, int $quantity = 1): array
    {
        return [[
            'product_id' => 1,
            'name'       => 'Test Product',
            'weight_kg'  => $weightKg,
            'quantity'   => $quantity,
        ]];
    }

    private function multiItem(): array
    {
        return [
            ['product_id' => 1, 'name' => 'Widget', 'weight_kg' => 0.5, 'quantity' => 2],
            ['product_id' => 2, 'name' => 'Gadget', 'weight_kg' => 1.0, 'quantity' => 1],
        ];
    }

    // =========================================================================
    // Result structure
    // =========================================================================

    public function test_result_contains_expected_keys()
    {
        $result = $this->calculator()->calculate($this->singleItem());

        $this->assertArrayHasKey('calculator_enabled', $result);
        $this->assertArrayHasKey('total_cost', $result);
        $this->assertArrayHasKey('is_free_shipping', $result);
        $this->assertArrayHasKey('free_shipping_info', $result);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertArrayHasKey('combined_weight', $result);
        $this->assertArrayHasKey('lines', $result);
        $this->assertArrayHasKey('warnings', $result);
    }

    public function test_calculator_enabled_is_true_when_active()
    {
        $result = $this->calculator()->calculate($this->singleItem());

        $this->assertTrue($result['calculator_enabled']);
    }

    public function test_total_cost_is_a_float()
    {
        $result = $this->calculator()->calculate($this->singleItem());

        $this->assertIsFloat($result['total_cost']);
    }

    public function test_total_cost_is_rounded_to_two_decimal_places()
    {
        $result = $this->calculator()->calculate($this->singleItem(0.333, 1));

        $this->assertEquals(
            round($result['total_cost'], 2),
            $result['total_cost']
        );
    }

    public function test_breakdown_is_an_array_of_labelled_amounts()
    {
        $result = $this->calculator()->calculate($this->singleItem());

        foreach ($result['breakdown'] as $line) {
            $this->assertArrayHasKey('label', $line);
            $this->assertArrayHasKey('amount', $line);
        }
    }

    public function test_lines_are_built_with_correct_shape()
    {
        $result = $this->calculator()->calculate($this->singleItem(0.5, 3));

        $line = $result['lines'][0];
        $this->assertArrayHasKey('product_id', $line);
        $this->assertArrayHasKey('name', $line);
        $this->assertArrayHasKey('unit_weight_kg', $line);
        $this->assertArrayHasKey('quantity', $line);
        $this->assertArrayHasKey('line_weight_kg', $line);
    }

    // =========================================================================
    // Line weight calculation
    // =========================================================================

    public function test_line_weight_is_unit_weight_multiplied_by_quantity()
    {
        $result = $this->calculator()->calculate($this->singleItem(0.5, 4));

        $this->assertEquals(2.0, $result['lines'][0]['line_weight_kg']);
    }

    public function test_combined_weight_sums_all_line_weights()
    {
        $cart = [
            ['product_id' => 1, 'name' => 'A', 'weight_kg' => 1.0, 'quantity' => 2],
            ['product_id' => 2, 'name' => 'B', 'weight_kg' => 0.5, 'quantity' => 4],
        ];

        $result = $this->calculator()->calculate($cart);

        $this->assertEquals(4.0, $result['combined_weight']);
    }

    public function test_combined_weight_is_rounded_to_three_decimal_places()
    {
        $result = $this->calculator()->calculate($this->singleItem(0.3333, 3));

        $this->assertEquals(round($result['combined_weight'], 3), $result['combined_weight']);
    }

    // =========================================================================
    // Base fee
    // =========================================================================

    public function test_base_fee_is_included_in_breakdown()
    {
        $result = $this->calculator()->calculate($this->singleItem());

        $labels = array_column($result['breakdown'], 'label');
        $this->assertContains('Base handling fee', $labels);
    }

    public function test_custom_base_fee_is_applied()
    {
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withBaseFee(5.00)
            ->withWeightBands([['max_kg' => PHP_FLOAT_MAX, 'rate_per_kg' => 0.0]])
            ->calculate($this->singleItem());

        $this->assertEquals(5.00, $result['total_cost']);
    }

    public function test_zero_base_fee_is_applied()
    {
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withBaseFee(0.00)
            ->withWeightBands([['max_kg' => PHP_FLOAT_MAX, 'rate_per_kg' => 0.0]])
            ->withMultiProductSurcharge(0.00)
            ->calculate($this->singleItem());

        $this->assertEquals(0.00, $result['total_cost']);
    }

    // =========================================================================
    // Weight bands
    // =========================================================================

    public function test_first_weight_band_applied_for_order_under_one_kg()
    {
        // 0.5 kg × £3.00 + £2.50 base = £4.00
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->singleItem(0.5, 1));

        $this->assertEquals(4.00, $result['total_cost']);
    }

    public function test_second_weight_band_applied_for_order_between_one_and_five_kg()
    {
        // 3.0 kg × £2.50 + £2.50 base = £10.00
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->singleItem(3.0, 1));

        $this->assertEquals(10.00, $result['total_cost']);
    }

    public function test_third_weight_band_applied_for_order_between_five_and_fifteen_kg()
    {
        // 10.0 kg × £2.00 + £2.50 base = £22.50
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->singleItem(10.0, 1));

        $this->assertEquals(22.50, $result['total_cost']);
    }

    public function test_fourth_weight_band_applied_for_order_between_fifteen_and_thirty_kg()
    {
        // 20.0 kg × £1.75 + £2.50 base = £37.50
        // Heavy item threshold raised above 20 kg to isolate the band rate logic
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withHeavyItemThreshold(25.0, 3.00)
            ->calculate($this->singleItem(20.0, 1));

        $this->assertEquals(37.50, $result['total_cost']);
    }

    public function test_final_weight_band_applied_for_order_over_thirty_kg()
    {
        // 35.0 kg × £1.50 + £2.50 base = £55.00
        // Heavy item threshold raised above 35 kg to isolate the band rate logic
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withHeavyItemThreshold(40.0, 3.00)
            ->calculate($this->singleItem(35.0, 1));

        $this->assertEquals(55.00, $result['total_cost']);
    }

    public function test_weight_band_boundary_uses_the_lower_band_at_exact_limit()
    {
        // Exactly 1.0 kg should use the first band (≤1.0 kg) at £3.00/kg
        // 1.0 × £3.00 + £2.50 base = £5.50
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->singleItem(1.0, 1));

        $this->assertEquals(5.50, $result['total_cost']);
    }

    public function test_custom_weight_bands_are_used()
    {
        $bands = [
            ['max_kg' => 10.0,          'rate_per_kg' => 1.00],
            ['max_kg' => PHP_FLOAT_MAX, 'rate_per_kg' => 0.50],
        ];

        // 2.0 kg × £1.00 + £2.50 base = £4.50
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withWeightBands($bands)
            ->calculate($this->singleItem(2.0, 1));

        $this->assertEquals(4.50, $result['total_cost']);
    }

    // =========================================================================
    // Multi-product surcharge
    // =========================================================================

    public function test_multi_product_surcharge_not_applied_for_single_product_line()
    {
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->singleItem(0.5, 3));

        $labels = array_column($result['breakdown'], 'label');
        $this->assertEmpty(array_filter($labels, fn ($l) => str_contains($l, 'Multi-product')));
    }

    public function test_multi_product_surcharge_applied_for_two_product_lines()
    {
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->multiItem());

        $labels = array_column($result['breakdown'], 'label');
        $this->assertNotEmpty(array_filter($labels, fn ($l) => str_contains($l, 'Multi-product')));
    }

    public function test_multi_product_surcharge_amount_is_correct()
    {
        // 2.0 kg combined × £2.50/kg + £2.50 base + £1.50 surcharge = £9.00
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->multiItem());

        $surchargeLines = array_filter(
            $result['breakdown'],
            fn ($l) => str_contains($l['label'], 'Multi-product')
        );

        $this->assertEquals(1.50, array_values($surchargeLines)[0]['amount']);
    }

    public function test_custom_multi_product_surcharge_is_applied()
    {
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withMultiProductSurcharge(3.00)
            ->calculate($this->multiItem());

        $surchargeLines = array_filter(
            $result['breakdown'],
            fn ($l) => str_contains($l['label'], 'Multi-product')
        );

        $this->assertEquals(3.00, array_values($surchargeLines)[0]['amount']);
    }

    public function test_multi_product_surcharge_applied_once_regardless_of_line_count()
    {
        $cart = [
            ['product_id' => 1, 'name' => 'A', 'weight_kg' => 0.5, 'quantity' => 1],
            ['product_id' => 2, 'name' => 'B', 'weight_kg' => 0.5, 'quantity' => 1],
            ['product_id' => 3, 'name' => 'C', 'weight_kg' => 0.5, 'quantity' => 1],
        ];

        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($cart);

        $surchargeLines = array_filter(
            $result['breakdown'],
            fn ($l) => str_contains($l['label'], 'Multi-product')
        );

        $this->assertCount(1, $surchargeLines);
    }

    // =========================================================================
    // Heavy item surcharge
    // =========================================================================

    public function test_heavy_item_surcharge_not_applied_when_line_weight_under_threshold()
    {
        // 9.9 kg line weight — just under the 10 kg threshold
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->singleItem(9.9, 1));

        $labels = array_column($result['breakdown'], 'label');
        $this->assertEmpty(array_filter($labels, fn ($l) => str_contains($l, 'Heavy item')));
    }

    public function test_heavy_item_surcharge_applied_when_line_weight_exceeds_threshold()
    {
        // 10.1 kg line weight — just over the 10 kg threshold
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->singleItem(10.1, 1));

        $labels = array_column($result['breakdown'], 'label');
        $this->assertNotEmpty(array_filter($labels, fn ($l) => str_contains($l, 'Heavy item')));
    }

    public function test_heavy_item_surcharge_uses_combined_line_weight_not_unit_weight()
    {
        // 3.5 kg unit × 3 qty = 10.5 kg line weight — should trigger surcharge
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->singleItem(3.5, 3));

        $labels = array_column($result['breakdown'], 'label');
        $this->assertNotEmpty(array_filter($labels, fn ($l) => str_contains($l, 'Heavy item')));
    }

    public function test_heavy_item_surcharge_applied_per_qualifying_line()
    {
        $cart = [
            ['product_id' => 1, 'name' => 'Heavy A', 'weight_kg' => 12.0, 'quantity' => 1],
            ['product_id' => 2, 'name' => 'Heavy B', 'weight_kg' => 15.0, 'quantity' => 1],
        ];

        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($cart);

        $heavyLines = array_filter(
            $result['breakdown'],
            fn ($l) => str_contains($l['label'], 'Heavy item')
        );

        $this->assertCount(2, $heavyLines);
    }

    public function test_custom_heavy_item_threshold_and_surcharge_are_applied()
    {
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withHeavyItemThreshold(5.0, 2.00)
            ->calculate($this->singleItem(6.0, 1));

        $heavyLines = array_filter(
            $result['breakdown'],
            fn ($l) => str_contains($l['label'], 'Heavy item')
        );

        $this->assertNotEmpty($heavyLines);
        $this->assertEquals(2.00, array_values($heavyLines)[0]['amount']);
    }

    public function test_line_weight_exactly_at_heavy_threshold_does_not_trigger_surcharge()
    {
        // Threshold is 10.0 kg; line weight = 10.0 kg exactly — should NOT trigger
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->singleItem(10.0, 1));

        $labels = array_column($result['breakdown'], 'label');
        $this->assertEmpty(array_filter($labels, fn ($l) => str_contains($l, 'Heavy item')));
    }

    // =========================================================================
    // Free shipping
    // =========================================================================

    public function test_free_shipping_applied_when_subtotal_meets_threshold()
    {
        $result = $this->calculator()->calculate($this->singleItem(), orderSubtotal: 75.00);

        $this->assertTrue($result['is_free_shipping']);
        $this->assertEquals(0.00, $result['total_cost']);
    }

    public function test_free_shipping_applied_when_subtotal_exceeds_threshold()
    {
        $result = $this->calculator()->calculate($this->singleItem(), orderSubtotal: 120.00);

        $this->assertTrue($result['is_free_shipping']);
        $this->assertEquals(0.00, $result['total_cost']);
    }

    public function test_free_shipping_not_applied_when_subtotal_below_threshold()
    {
        $result = $this->calculator()->calculate($this->singleItem(), orderSubtotal: 74.99);

        $this->assertFalse($result['is_free_shipping']);
        $this->assertGreaterThan(0.00, $result['total_cost']);
    }

    public function test_free_shipping_info_remaining_is_correct_when_not_yet_qualified()
    {
        $result = $this->calculator()->calculate($this->singleItem(), orderSubtotal: 55.00);

        $this->assertEquals(20.00, $result['free_shipping_info']['remaining']);
    }

    public function test_free_shipping_info_remaining_is_zero_when_qualified()
    {
        $result = $this->calculator()->calculate($this->singleItem(), orderSubtotal: 80.00);

        $this->assertEquals(0.00, $result['free_shipping_info']['remaining']);
    }

    public function test_free_shipping_info_progress_pct_is_correct()
    {
        $result = $this->calculator()->calculate($this->singleItem(), orderSubtotal: 37.50);

        $this->assertEquals(50.0, $result['free_shipping_info']['progress_pct']);
    }

    public function test_free_shipping_info_progress_pct_capped_at_100()
    {
        $result = $this->calculator()->calculate($this->singleItem(), orderSubtotal: 200.00);

        $this->assertEquals(100.0, $result['free_shipping_info']['progress_pct']);
    }

    public function test_free_shipping_blocked_by_weight_when_weight_limit_exceeded()
    {
        $calculator = (new ShippingCalculator())
            ->withFreeShipping(threshold: 75.00, weightLimitKg: 20.0);

        // Subtotal qualifies but weight exceeds the limit
        $result = $calculator->calculate($this->singleItem(25.0, 1), orderSubtotal: 100.00);

        $this->assertFalse($result['is_free_shipping']);
        $this->assertTrue($result['free_shipping_info']['blocked_by_weight']);
    }

    public function test_free_shipping_granted_when_weight_within_limit()
    {
        $calculator = (new ShippingCalculator())
            ->withFreeShipping(threshold: 75.00, weightLimitKg: 20.0);

        $result = $calculator->calculate($this->singleItem(5.0, 1), orderSubtotal: 100.00);

        $this->assertTrue($result['is_free_shipping']);
        $this->assertFalse($result['free_shipping_info']['blocked_by_weight']);
    }

    public function test_free_shipping_disabled_via_without_free_shipping()
    {
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->singleItem(), orderSubtotal: 500.00);

        $this->assertFalse($result['is_free_shipping']);
        $this->assertGreaterThan(0.00, $result['total_cost']);
    }

    public function test_free_shipping_info_standalone_returns_correct_data()
    {
        $info = $this->calculator()->freeShippingInfo(orderSubtotal: 55.00);

        $this->assertFalse($info['is_free']);
        $this->assertEquals(75.00, $info['threshold']);
        $this->assertEquals(55.00, $info['subtotal']);
        $this->assertEquals(20.00, $info['remaining']);
        $this->assertEqualsWithDelta(73.3, $info['progress_pct'], 0.1);
    }

    public function test_free_shipping_info_standalone_returns_is_free_true_when_qualified()
    {
        $info = $this->calculator()->freeShippingInfo(orderSubtotal: 80.00);

        $this->assertTrue($info['is_free']);
        $this->assertEquals(0.00, $info['remaining']);
    }

    // =========================================================================
    // Overweight warning
    // =========================================================================

    public function test_no_warning_when_combined_weight_is_well_under_maximum()
    {
        $result = $this->calculator()->calculate($this->singleItem(5.0, 1));

        $this->assertEmpty($result['warnings']);
    }

    public function test_warning_issued_when_combined_weight_is_over_90_percent_of_maximum()
    {
        // Default max is 100 kg — 91 kg should trigger the warning
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->calculate($this->singleItem(91.0, 1));

        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('91.00 kg', $result['warnings'][0]);
    }

    public function test_custom_max_shippable_weight_triggers_warning_at_correct_threshold()
    {
        // Max set to 50 kg — 46 kg (92%) should trigger
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withMaxShippableWeight(50.0)
            ->calculate($this->singleItem(46.0, 1));

        $this->assertNotEmpty($result['warnings']);
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_exception_thrown_for_empty_cart()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cart must contain at least one item.');

        $this->calculator()->calculate([]);
    }

    public function test_exception_thrown_for_zero_weight()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid weight_kg');

        $this->calculator()->calculate([
            ['product_id' => 1, 'name' => 'Bad', 'weight_kg' => 0, 'quantity' => 1],
        ]);
    }

    public function test_exception_thrown_for_negative_weight()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator()->calculate([
            ['product_id' => 1, 'name' => 'Bad', 'weight_kg' => -1.0, 'quantity' => 1],
        ]);
    }

    public function test_exception_thrown_for_zero_quantity()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid quantity');

        $this->calculator()->calculate([
            ['product_id' => 1, 'name' => 'Bad', 'weight_kg' => 1.0, 'quantity' => 0],
        ]);
    }

    public function test_exception_thrown_when_combined_weight_exceeds_maximum()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the maximum shippable weight');

        $this->calculator()->calculate($this->singleItem(101.0, 1));
    }

    public function test_exception_thrown_when_multiple_items_combined_exceed_maximum()
    {
        $this->expectException(\InvalidArgumentException::class);

        $cart = [
            ['product_id' => 1, 'name' => 'A', 'weight_kg' => 60.0, 'quantity' => 1],
            ['product_id' => 2, 'name' => 'B', 'weight_kg' => 60.0, 'quantity' => 1],
        ];

        $this->calculator()->calculate($cart);
    }

    public function test_item_at_exact_maximum_weight_does_not_throw()
    {
        $result = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->withMaxShippableWeight(50.0)
            ->calculate($this->singleItem(50.0, 1));

        $this->assertEquals(50.0, $result['combined_weight']);
    }

    // =========================================================================
    // Disabled — free mode
    // =========================================================================

    public function test_disabled_calculator_returns_calculator_enabled_false()
    {
        $result = (new ShippingCalculator())
            ->disable('free')
            ->calculate($this->singleItem());

        $this->assertFalse($result['calculator_enabled']);
    }

    public function test_disabled_free_mode_returns_zero_total_cost()
    {
        $result = (new ShippingCalculator())
            ->disable('free', message: 'Free shipping weekend!')
            ->calculate($this->singleItem());

        $this->assertEquals(0.00, $result['total_cost']);
        $this->assertTrue($result['is_free_shipping']);
    }

    public function test_disabled_free_mode_includes_message_in_breakdown()
    {
        $result = (new ShippingCalculator())
            ->disable('free', message: 'Free shipping weekend!')
            ->calculate($this->singleItem());

        $this->assertEquals('Free shipping weekend!', $result['breakdown'][0]['label']);
    }

    // =========================================================================
    // Disabled — flat rate mode
    // =========================================================================

    public function test_disabled_flat_rate_mode_returns_configured_amount()
    {
        $result = (new ShippingCalculator())
            ->disable('flat_rate', flatRate: 4.99)
            ->calculate($this->singleItem());

        $this->assertEquals(4.99, $result['total_cost']);
        $this->assertFalse($result['is_free_shipping']);
    }

    public function test_disabled_flat_rate_mode_returns_calculator_enabled_false()
    {
        $result = (new ShippingCalculator())
            ->disable('flat_rate', flatRate: 9.99)
            ->calculate($this->singleItem());

        $this->assertFalse($result['calculator_enabled']);
    }

    public function test_disabled_flat_rate_includes_message_in_breakdown()
    {
        $result = (new ShippingCalculator())
            ->disable('flat_rate', flatRate: 5.99, message: 'Standard flat rate applies')
            ->calculate($this->singleItem());

        $this->assertEquals('Standard flat rate applies', $result['breakdown'][0]['label']);
        $this->assertEquals(5.99, $result['breakdown'][0]['amount']);
    }

    // =========================================================================
    // Disabled — unavailable mode
    // =========================================================================

    public function test_disabled_unavailable_mode_throws_runtime_exception()
    {
        $this->expectException(\RuntimeException::class);

        (new ShippingCalculator())
            ->disable('unavailable', message: 'Shipping unavailable.')
            ->calculate($this->singleItem());
    }

    public function test_disabled_unavailable_mode_exception_contains_message()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Currently not shipping.');

        (new ShippingCalculator())
            ->disable('unavailable', message: 'Currently not shipping.')
            ->calculate($this->singleItem());
    }

    // =========================================================================
    // Disabled — validation is skipped
    // =========================================================================

    public function test_disabled_free_mode_does_not_validate_cart()
    {
        // An empty cart would normally throw — disabled mode should bypass validation
        $result = (new ShippingCalculator())
            ->disable('free')
            ->calculate([]);

        $this->assertFalse($result['calculator_enabled']);
        $this->assertEquals(0.00, $result['total_cost']);
    }

    public function test_disabled_flat_rate_mode_does_not_validate_cart()
    {
        $result = (new ShippingCalculator())
            ->disable('flat_rate', flatRate: 5.00)
            ->calculate([]);

        $this->assertEquals(5.00, $result['total_cost']);
    }

    // =========================================================================
    // Disabled — freeShippingInfo returns correctly
    // =========================================================================

    public function test_free_shipping_info_returns_disabled_state_when_calculator_off()
    {
        $info = (new ShippingCalculator())
            ->disable('free')
            ->freeShippingInfo(orderSubtotal: 100.00);

        $this->assertFalse($info['is_free']);
        $this->assertFalse($info['enabled']);
        $this->assertEquals(0.0, $info['remaining']);
        $this->assertEquals(0.0, $info['progress_pct']);
    }

    // =========================================================================
    // isEnabled()
    // =========================================================================

    public function test_is_enabled_returns_true_by_default()
    {
        $this->assertTrue((new ShippingCalculator())->isEnabled());
    }

    public function test_is_enabled_returns_false_after_disable()
    {
        $calculator = (new ShippingCalculator())->disable('free');

        $this->assertFalse($calculator->isEnabled());
    }

    public function test_is_enabled_returns_true_after_re_enabling()
    {
        $calculator = (new ShippingCalculator())->disable('free')->enable();

        $this->assertTrue($calculator->isEnabled());
    }

    public function test_re_enabled_calculator_performs_full_calculation()
    {
        $calculator = (new ShippingCalculator())
            ->withoutFreeShipping()
            ->disable('free')
            ->enable();

        $result = $calculator->calculate($this->singleItem(0.5, 1));

        $this->assertTrue($result['calculator_enabled']);
        $this->assertGreaterThan(0.00, $result['total_cost']);
    }

    // =========================================================================
    // Fluent setters return static
    // =========================================================================

    public function test_fluent_setters_return_same_instance()
    {
        $calculator = new ShippingCalculator();

        $this->assertSame($calculator, $calculator->withBaseFee(3.00));
        $this->assertSame($calculator, $calculator->withMultiProductSurcharge(2.00));
        $this->assertSame($calculator, $calculator->withHeavyItemThreshold(8.0, 4.00));
        $this->assertSame($calculator, $calculator->withMaxShippableWeight(50.0));
        $this->assertSame($calculator, $calculator->withFreeShipping(50.00));
        $this->assertSame($calculator, $calculator->withoutFreeShipping());
        $this->assertSame($calculator, $calculator->disable('free'));
        $this->assertSame($calculator, $calculator->enable());
    }
}
