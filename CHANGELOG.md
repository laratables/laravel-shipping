# Changelog

All notable changes to `laratables/laravel-shipping` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2025-01-01

### Added

- `ShippingCalculator` service — weight-based shipping cost calculation with configurable weight bands, base handling fee, multi-product surcharge, and heavy item surcharge
- `ShippingResolver` service — bridges the session cart and `ShippingCalculator`, routing products to the weighted pool (algorithm) or flat-rate pool based on the presence of `weight_kg`
- `ShippingWeightBand` Eloquent model — database-driven weight bands with `active` scope and `toCalculatorBands()` static helper
- `ShippingServiceProvider` — auto-discovered service provider registering `ShippingCalculator` and `ShippingResolver` as singletons with config and DB-driven weight bands
- `Shipping` facade — proxy to `ShippingResolver` for convenient static access
- Database migration for `shipping_weight_bands` table with `max_kg`, `rate_per_kg`, `label`, `sort_order`, and `active` columns
- `ShippingWeightBandSeeder` — seeds five default weight bands (up to 1 kg, 1–5 kg, 5–15 kg, 15–30 kg, 30 kg+)
- `config/shipping.php` — full configuration file covering enabled toggle, disabled fallback modes, base fee, surcharges, max weight, and free shipping threshold
- Three disabled fallback modes — `free`, `flat_rate`, and `unavailable`
- Free shipping threshold with optional weight cap via `free_weight_limit_kg`
- Per-product priority rule — `weight_kg` takes precedence over `shipping_cost` on the same product
- `estimateForProduct()` method on `ShippingResolver` for single-product shipping estimates on product pages
- `freeShippingInfo()` standalone method for cart sidebar progress bars without a full calculation
- Overweight advisory warning when combined order weight exceeds 90% of `max_weight_kg`
- Full fluent configuration API on `ShippingCalculator` — `withBaseFee()`, `withWeightBands()`, `withFreeShipping()`, `withHeavyItemThreshold()`, `disable()`, `enable()`, and more
- 60 unit tests for `ShippingCalculator` covering all calculation scenarios, weight bands, surcharges, free shipping, disabled modes, and validation
- 40 unit tests for `ShippingResolver` covering the three routing scenarios, priority rule, mixed carts, disabled modes, and free shipping
- 30 feature tests for `ShippingWeightBand` covering DB queries, active scope, ordering, end-to-end integration with the calculator, container resolution, and cache behaviour
- Orchestra Testbench-based `TestCase` for running package tests without a full Laravel application
- Support for Laravel 10, 11, and 12
- Support for PHP 8.2+

---

[1.0.0]: https://github.com/laratables/laravel-shipping/releases/tag/v1.0.0
