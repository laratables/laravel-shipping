<?php

namespace Laratables\Shipping\Services;

use Illuminate\Support\Facades\Log;

class ShippingResolver
{
    public function __construct(protected ShippingCalculator $calculator) {}

    public function resolve(array $cart, float $orderSubtotal = 0.00): array
    {
        if (empty($cart)) return $this->emptyResult();

        if (! $this->calculator->isEnabled()) return $this->disabledResult($orderSubtotal);

        ['weighted' => $weighted, 'flat' => $flat] = $this->splitCart($cart);

        if (empty($weighted) && empty($flat)) return $this->emptyResult();

        $algorithmResult = null;
        $algorithmTotal  = 0.00;
        $warnings        = [];

        if (! empty($weighted)) {
            try {
                $algorithmResult = $this->calculator->calculate($weighted, $orderSubtotal);
                $algorithmTotal  = $algorithmResult['total_cost'];
                $warnings        = $algorithmResult['warnings'] ?? [];
            } catch (\InvalidArgumentException $e) {
                Log::warning('ShippingResolver: calculator rejected weighted items.', ['error' => $e->getMessage()]);
                $warnings[] = $e->getMessage();
            }
        }

        $flatTotal = 0.00;
        $flatLines = [];
        foreach ($flat as $item) {
            $itemFlat  = (float) ($item['shipping_cost'] ?? 0.00);
            $qty       = (int)   ($item['quantity']      ?? 1);
            $lineTotal = $itemFlat;
            $flatTotal  += $lineTotal;
            $flatLines[] = ['name' => $item['name'] ?? 'Unknown product', 'quantity' => $qty, 'shipping_cost' => $itemFlat, 'line_total' => $lineTotal];
        }

        $freeShippingInfo = $algorithmResult['free_shipping_info'] ?? $this->calculator->freeShippingInfo($orderSubtotal);
        $isFree = $algorithmResult['is_free_shipping'] ?? false;

        return [
            'enabled'             => true,
            'total_shipping'      => round($algorithmTotal + $flatTotal, 2),
            'algorithm_total'     => round($algorithmTotal, 2),
            'flat_total'          => round($flatTotal, 2),
            'is_free_shipping'    => $isFree,
            'free_shipping_info'  => $freeShippingInfo,
            'algorithm_result'    => $algorithmResult,
            'flat_items'          => $flatLines,
            'weighted_item_count' => count($weighted),
            'flat_item_count'     => count($flat),
            'has_shippable_items' => true,
            'warnings'            => $warnings,
        ];
    }

    public function freeShippingInfo(float $orderSubtotal): array
    {
        return $this->calculator->freeShippingInfo($orderSubtotal);
    }

    public function estimateForProduct(int|string $productId, string $name, ?float $weightKg = null, ?float $shippingCost = null, int $quantity = 1, float $orderSubtotal = 0.00): array
    {
        return $this->resolve([['product_id' => $productId, 'name' => $name, 'weight_kg' => $weightKg, 'shipping_cost' => $shippingCost, 'quantity' => $quantity, 'price' => $orderSubtotal]], $orderSubtotal);
    }

    protected function splitCart(array $cart): array
    {
        $weighted = [];
        $flat     = [];
        foreach ($cart as $item) {
            $weightKg     = isset($item['weight_kg'])     && $item['weight_kg']     !== null ? (float) $item['weight_kg']     : null;
            $shippingCost = isset($item['shipping_cost']) && $item['shipping_cost'] !== null ? (float) $item['shipping_cost'] : null;
            if ($weightKg !== null && $weightKg > 0) {
                $weighted[] = ['product_id' => $item['product_id'] ?? null, 'name' => $item['name'] ?? 'Unknown product', 'weight_kg' => $weightKg, 'quantity' => (int) ($item['quantity'] ?? 1)];
            } elseif ($shippingCost !== null) {
                $flat[] = $item;
            }
        }
        return ['weighted' => $weighted, 'flat' => $flat];
    }

    protected function disabledResult(float $orderSubtotal): array
    {
        try {
            $fallback = $this->calculator->calculate([['product_id' => 0, 'name' => '', 'weight_kg' => 1.0, 'quantity' => 1]], $orderSubtotal);
        } catch (\RuntimeException $e) {
            return array_merge($this->emptyResult(), ['enabled' => false, 'warnings' => [$e->getMessage()]]);
        }
        return ['enabled' => false, 'total_shipping' => $fallback['total_cost'], 'algorithm_total' => $fallback['total_cost'], 'flat_total' => 0.00, 'is_free_shipping' => $fallback['is_free_shipping'], 'free_shipping_info' => $fallback['free_shipping_info'], 'algorithm_result' => $fallback, 'flat_items' => [], 'weighted_item_count' => 0, 'flat_item_count' => 0, 'has_shippable_items' => true, 'warnings' => $fallback['warnings'] ?? []];
    }

    protected function emptyResult(): array
    {
        return ['enabled' => $this->calculator->isEnabled(), 'total_shipping' => 0.00, 'algorithm_total' => 0.00, 'flat_total' => 0.00, 'is_free_shipping' => false, 'free_shipping_info' => $this->calculator->freeShippingInfo(0.00), 'algorithm_result' => null, 'flat_items' => [], 'weighted_item_count' => 0, 'flat_item_count' => 0, 'has_shippable_items' => false, 'warnings' => []];
    }
}
