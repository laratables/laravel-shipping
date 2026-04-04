# laratables/laravel-shipping

A weight-based shipping cost calculator for Laravel with database-driven weight bands, free shipping thresholds, flat-rate fallback, and a global on/off toggle.

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12

## Installation

```bash
composer require laratables/laravel-shipping
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag=shipping-migrations
php artisan migrate
```

Publish the config file:

```bash
php artisan vendor:publish --tag=shipping-config
```

Seed the default weight bands:

```bash
php artisan vendor:publish --tag=shipping-seeders
php artisan db:seed --class=ShippingWeightBandSeeder
```

## Usage

### Resolving shipping for a cart

```php
use Laratables\Shipping\Services\ShippingResolver;

$result = app(ShippingResolver::class)->resolve(
    cart:          session('cart', []),
    orderSubtotal: 55.00,
);

$result['total_shipping'];     // float — final charge
$result['is_free_shipping'];   // bool
$result['free_shipping_info']; // progress bar data
$result['breakdown'];          // itemised lines
```

### Cart item shape

```php
[
    'product_id'    => 1,
    'name'          => 'T-Shirt',
    'weight_kg'     => 0.5,    // null to use flat rate
    'shipping_cost' => null,   // used when weight_kg is null
    'quantity'      => 2,
    'price'         => 19.99,
]
```

### Per-product priority rule

| weight_kg | shipping_cost | Result |
|-----------|--------------|--------|
| set | any | Algorithm used, shipping_cost ignored |
| null | set | Flat rate used directly |
| null | null | Not shippable, skipped |

### Product estimate

```php
$estimate = app(ShippingResolver::class)->estimateForProduct(
    productId:    $product->id,
    name:         $product->name,
    weightKg:     $product->weight_kg,
    shippingCost: $product->shipping_cost,
    quantity:     1,
);
```

### Using the Facade

```php
use Laratables\Shipping\Facades\Shipping;

$result = Shipping::resolve(session('cart', []), $subtotal);
$info   = Shipping::freeShippingInfo($subtotal);
```

## Configuration

All settings are in `config/shipping.php` and driven by environment variables:

| Key | .env | Default | Description |
|-----|------|---------|-------------|
| `enabled` | `SHIPPING_ENABLED` | `true` | Master on/off toggle |
| `disabled_fallback.mode` | `SHIPPING_DISABLED_MODE` | `free` | `free`, `flat_rate`, or `unavailable` |
| `disabled_fallback.flat_rate_amount` | `SHIPPING_FLAT_RATE_AMOUNT` | `5.99` | Fixed charge when mode is `flat_rate` |
| `base_fee` | `SHIPPING_BASE_FEE` | `2.50` | Handling fee on every order |
| `multi_product_surcharge` | `SHIPPING_MULTI_PRODUCT_SURCHARGE` | `1.50` | Added when cart has >1 product line |
| `heavy_item_threshold_kg` | `SHIPPING_HEAVY_ITEM_THRESHOLD_KG` | `10.0` | Line weight that triggers heavy surcharge |
| `heavy_item_surcharge` | `SHIPPING_HEAVY_ITEM_SURCHARGE` | `3.00` | Charge per heavy product line |
| `max_weight_kg` | `SHIPPING_MAX_WEIGHT_KG` | `100.0` | Maximum shippable order weight |
| `free_enabled` | `SHIPPING_FREE_ENABLED` | `true` | Enable free shipping threshold |
| `free_threshold` | `SHIPPING_FREE_THRESHOLD` | `75.00` | Subtotal needed for free shipping |
| `free_weight_limit_kg` | `SHIPPING_FREE_WEIGHT_LIMIT_KG` | `null` | Max weight still eligible for free shipping |

## Weight bands

Bands are stored in `shipping_weight_bands` and managed from your admin panel. After updating bands, clear the cache:

```php
Cache::forget('shipping_weight_bands');
```

## Disabled modes

```env
SHIPPING_ENABLED=false
SHIPPING_DISABLED_MODE=flat_rate
SHIPPING_FLAT_RATE_AMOUNT=5.99
SHIPPING_DISABLED_MESSAGE="Flat rate shipping applied"
```

| Mode | Behaviour |
|------|-----------|
| `free` | All orders ship free |
| `flat_rate` | Fixed charge on every order |
| `unavailable` | Throws `RuntimeException`, block checkout |

## Testing

```bash
composer test
```

## License

MIT
