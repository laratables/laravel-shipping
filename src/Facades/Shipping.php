<?php

namespace Laratables\Shipping\Facades;

use Illuminate\Support\Facades\Facade;

class Shipping extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Laratables\Shipping\Services\ShippingResolver::class;
    }
}
