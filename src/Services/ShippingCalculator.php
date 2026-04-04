<?php

namespace Laratables\Shipping\Services;

class ShippingCalculator
{
    protected bool   $enabled             = true;
    protected string $disabledMode        = 'free';
    protected float  $disabledFlatRate    = 5.99;
    protected string $disabledMessage     = 'Shipping is currently unavailable.';
    protected float  $baseFee             = 2.50;
    protected array  $weightBands         = [
        ['max_kg' => 1.0,           'rate_per_kg' => 3.00],
        ['max_kg' => 5.0,           'rate_per_kg' => 2.50],
        ['max_kg' => 15.0,          'rate_per_kg' => 2.00],
        ['max_kg' => 30.0,          'rate_per_kg' => 1.75],
        ['max_kg' => PHP_FLOAT_MAX, 'rate_per_kg' => 1.50],
    ];
    protected float      $multiProductSurcharge    = 1.50;
    protected float      $heavyItemThresholdKg     = 10.0;
    protected float      $heavyItemSurcharge       = 3.00;
    protected float      $maxShippableKg           = 100.0;
    protected bool       $freeShippingEnabled      = true;
    protected float      $freeShippingThreshold    = 75.00;
    protected float|null $freeShippingWeightLimitKg = null;

    public function calculate(array $cartItems, float $orderSubtotal = 0.00): array
    {
        if (! $this->enabled) {
            return $this->disabledResult($cartItems, $orderSubtotal);
        }

        $this->validateCart($cartItems);

        $breakdown = [];
        $warnings  = [];
        $lines          = $this->buildLines($cartItems);
        $combinedWeight = $this->combinedWeight($lines);
        $freeShippingInfo = $this->freeShippingInfo($orderSubtotal, $combinedWeight);

        if ($freeShippingInfo['is_free']) {
            return [
                'calculator_enabled' => true,
                'total_cost'         => 0.00,
                'is_free_shipping'   => true,
                'free_shipping_info' => $freeShippingInfo,
                'breakdown'          => [[
                    'label'  => sprintf('Free shipping applied (order subtotal £%.2f meets £%.2f threshold)', $orderSubtotal, $this->freeShippingThreshold),
                    'amount' => 0.00,
                ]],
                'combined_weight'    => round($combinedWeight, 3),
                'lines'              => $lines,
                'warnings'           => $warnings,
            ];
        }

        $breakdown[] = ['label' => 'Base handling fee', 'amount' => $this->baseFee];

        $weightCharge = $this->weightCharge($combinedWeight);
        $breakdown[]  = [
            'label'  => sprintf('Weight charge (%.3f kg × £%.2f/kg band)', $combinedWeight, $combinedWeight > 0 ? $weightCharge / $combinedWeight : 0),
            'amount' => $weightCharge,
        ];

        $multiSurcharge = 0.0;
        if (count($lines) > 1) {
            $multiSurcharge = $this->multiProductSurcharge;
            $breakdown[]    = ['label' => sprintf('Multi-product surcharge (%d product lines)', count($lines)), 'amount' => $multiSurcharge];
        }

        $heavySurcharge = 0.0;
        foreach ($lines as $line) {
            if ($line['line_weight_kg'] > $this->heavyItemThresholdKg) {
                $heavySurcharge += $this->heavyItemSurcharge;
                $breakdown[]     = ['label' => sprintf('Heavy item surcharge — %s (%.2f kg)', $line['name'], $line['line_weight_kg']), 'amount' => $this->heavyItemSurcharge];
            }
        }

        if ($combinedWeight > $this->maxShippableKg * 0.9) {
            $warnings[] = sprintf('Order weight (%.2f kg) is close to or exceeds the %.0f kg shipping limit.', $combinedWeight, $this->maxShippableKg);
        }

        $total = $this->baseFee + $weightCharge + $multiSurcharge + $heavySurcharge;

        return [
            'calculator_enabled' => true,
            'total_cost'         => round($total, 2),
            'is_free_shipping'   => false,
            'free_shipping_info' => $freeShippingInfo,
            'breakdown'          => $breakdown,
            'combined_weight'    => round($combinedWeight, 3),
            'lines'              => $lines,
            'warnings'           => $warnings,
        ];
    }

    public function freeShippingInfo(float $orderSubtotal, float $combinedWeight = 0.0): array
    {
        if (! $this->enabled) {
            return ['is_free' => false, 'enabled' => false, 'threshold' => 0.0, 'subtotal' => $orderSubtotal, 'remaining' => 0.0, 'progress_pct' => 0.0, 'blocked_by_weight' => false, 'weight_limit_kg' => null];
        }

        $threshold       = $this->freeShippingThreshold;
        $weightLimit     = $this->freeShippingWeightLimitKg;
        $meetsValue      = $this->freeShippingEnabled && $threshold > 0 && $orderSubtotal >= $threshold;
        $blockedByWeight = $meetsValue && $weightLimit !== null && $combinedWeight > $weightLimit;
        $isFree          = $meetsValue && ! $blockedByWeight;
        $remaining       = $this->freeShippingEnabled && $threshold > 0 ? max(0.0, round($threshold - $orderSubtotal, 2)) : 0.0;
        $progressPct     = ($this->freeShippingEnabled && $threshold > 0) ? min(100, round(($orderSubtotal / $threshold) * 100, 1)) : 0.0;

        return ['is_free' => $isFree, 'enabled' => $this->freeShippingEnabled, 'threshold' => $threshold, 'subtotal' => $orderSubtotal, 'remaining' => $remaining, 'progress_pct' => $progressPct, 'blocked_by_weight' => $blockedByWeight, 'weight_limit_kg' => $weightLimit];
    }

    public function isEnabled(): bool { return $this->enabled; }

    public function withBaseFee(float $fee): static                                              { $this->baseFee = $fee; return $this; }
    public function withWeightBands(array $bands): static                                        { $this->weightBands = $bands; return $this; }
    public function withMultiProductSurcharge(float $amount): static                             { $this->multiProductSurcharge = $amount; return $this; }
    public function withHeavyItemThreshold(float $kg, float $surcharge): static                  { $this->heavyItemThresholdKg = $kg; $this->heavyItemSurcharge = $surcharge; return $this; }
    public function withMaxShippableWeight(float $kg): static                                    { $this->maxShippableKg = $kg; return $this; }
    public function withFreeShipping(float $threshold, float|null $weightLimitKg = null): static { $this->freeShippingEnabled = true; $this->freeShippingThreshold = $threshold; $this->freeShippingWeightLimitKg = $weightLimitKg; return $this; }
    public function withoutFreeShipping(): static                                                 { $this->freeShippingEnabled = false; return $this; }

    public function disable(string $mode = 'free', float $flatRate = 5.99, string $message = 'Shipping is currently unavailable.'): static
    {
        $this->enabled = false; $this->disabledMode = $mode; $this->disabledFlatRate = $flatRate; $this->disabledMessage = $message;
        return $this;
    }

    public function enable(): static { $this->enabled = true; return $this; }

    protected function disabledResult(array $cartItems, float $orderSubtotal): array
    {
        $base = ['calculator_enabled' => false, 'disabled_mode' => $this->disabledMode, 'message' => $this->disabledMessage, 'is_free_shipping' => false, 'free_shipping_info' => $this->freeShippingInfo($orderSubtotal), 'breakdown' => [], 'combined_weight' => null, 'lines' => [], 'warnings' => []];

        return match ($this->disabledMode) {
            'free'        => array_merge($base, ['total_cost' => 0.00, 'is_free_shipping' => true, 'breakdown' => [['label' => $this->disabledMessage ?: 'Free shipping — calculator disabled', 'amount' => 0.00]]]),
            'flat_rate'   => array_merge($base, ['total_cost' => round($this->disabledFlatRate, 2), 'breakdown' => [['label' => $this->disabledMessage ?: 'Flat rate shipping', 'amount' => round($this->disabledFlatRate, 2)]]]),
            'unavailable' => throw new \RuntimeException($this->disabledMessage),
            default       => throw new \RuntimeException("Unknown disabled shipping mode: {$this->disabledMode}"),
        };
    }

    protected function validateCart(array $cartItems): void
    {
        if (empty($cartItems)) throw new \InvalidArgumentException('Cart must contain at least one item.');
        foreach ($cartItems as $index => $item) {
            if (empty($item['weight_kg']) || $item['weight_kg'] <= 0) throw new \InvalidArgumentException("Item at index {$index} has an invalid weight_kg.");
            if (empty($item['quantity']) || $item['quantity'] < 1)    throw new \InvalidArgumentException("Item at index {$index} has an invalid quantity.");
        }
        $combined = array_sum(array_map(fn ($i) => $i['weight_kg'] * $i['quantity'], $cartItems));
        if ($combined > $this->maxShippableKg) throw new \InvalidArgumentException(sprintf('Combined order weight (%.2f kg) exceeds the maximum shippable weight of %.0f kg.', $combined, $this->maxShippableKg));
    }

    protected function buildLines(array $cartItems): array
    {
        return array_map(fn (array $item): array => ['product_id' => $item['product_id'] ?? null, 'name' => $item['name'] ?? 'Unknown product', 'unit_weight_kg' => $item['weight_kg'], 'quantity' => $item['quantity'], 'line_weight_kg' => $item['weight_kg'] * $item['quantity']], $cartItems);
    }

    protected function combinedWeight(array $lines): float { return array_sum(array_column($lines, 'line_weight_kg')); }

    protected function weightCharge(float $kg): float
    {
        foreach ($this->weightBands as $band) { if ($kg <= $band['max_kg']) return $kg * $band['rate_per_kg']; }
        $last = end($this->weightBands);
        return $kg * $last['rate_per_kg'];
    }
}
