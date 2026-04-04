<?php

namespace Laratables\Shipping\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laratables\Shipping\Providers\ShippingServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            ShippingServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('shipping.enabled',                        true);
        $app['config']->set('shipping.base_fee',                       2.50);
        $app['config']->set('shipping.multi_product_surcharge',        1.50);
        $app['config']->set('shipping.heavy_item_threshold_kg',        10.0);
        $app['config']->set('shipping.heavy_item_surcharge',           3.00);
        $app['config']->set('shipping.max_weight_kg',                  100.0);
        $app['config']->set('shipping.free_enabled',                   false);
        $app['config']->set('shipping.free_threshold',                 75.00);
        $app['config']->set('shipping.free_weight_limit_kg',           null);
        $app['config']->set('shipping.disabled_fallback.mode',         'free');
        $app['config']->set('shipping.disabled_fallback.flat_rate_amount', 5.99);
        $app['config']->set('shipping.disabled_fallback.message',      'Shipping unavailable.');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../src/Database/migrations');
    }
}
