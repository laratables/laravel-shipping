<?php

namespace Laratables\Shipping\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Laratables\Shipping\Models\ShippingWeightBand;
use Laratables\Shipping\Services\ShippingCalculator;
use Laratables\Shipping\Services\ShippingResolver;

class ShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/shipping.php', 'shipping');

        $this->app->singleton(ShippingCalculator::class, function () {
            $calculator = new ShippingCalculator();

            if (! (bool) filter_var(config('shipping.enabled'), FILTER_VALIDATE_BOOLEAN)) {
                return $calculator->disable(
                    mode:     (string) config('shipping.disabled_fallback.mode', 'free'),
                    flatRate: (float)  config('shipping.disabled_fallback.flat_rate_amount', 5.99),
                    message:  (string) config('shipping.disabled_fallback.message', 'Shipping unavailable.'),
                );
            }

            $bands = Cache::remember(
                'shipping_weight_bands',
                now()->addHour(),
                fn () => ShippingWeightBand::toCalculatorBands()
            );

            $calculator
                ->withBaseFee((float) config('shipping.base_fee'))
                ->withMultiProductSurcharge((float) config('shipping.multi_product_surcharge'))
                ->withHeavyItemThreshold(
                    (float) config('shipping.heavy_item_threshold_kg'),
                    (float) config('shipping.heavy_item_surcharge')
                )
                ->withMaxShippableWeight((float) config('shipping.max_weight_kg'))
                ->withWeightBands($bands);

            if ((bool) filter_var(config('shipping.free_enabled'), FILTER_VALIDATE_BOOLEAN)) {
                $weightLimit = config('shipping.free_weight_limit_kg');
                $calculator->withFreeShipping(
                    threshold:     (float) config('shipping.free_threshold'),
                    weightLimitKg: $weightLimit !== null ? (float) $weightLimit : null,
                );
            } else {
                $calculator->withoutFreeShipping();
            }

            return $calculator;
        });

        $this->app->singleton(ShippingResolver::class, fn ($app) => new ShippingResolver($app->make(ShippingCalculator::class)));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/shipping.php' => config_path('shipping.php'),
        ], 'shipping-config');

        $this->publishes([
            __DIR__ . '/../Database/migrations/' => database_path('migrations'),
        ], 'shipping-migrations');

        $this->publishes([
            __DIR__ . '/../Database/seeders/' => database_path('seeders'),
        ], 'shipping-seeders');

        $this->loadMigrationsFrom(__DIR__ . '/../Database/migrations');
    }
}
