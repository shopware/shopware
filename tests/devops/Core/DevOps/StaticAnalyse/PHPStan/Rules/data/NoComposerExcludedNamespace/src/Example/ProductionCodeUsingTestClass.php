<?php

declare(strict_types=1);

use Shopware\Core\Test\TestDefaults;

class ProductionCodeUsingTestClass
{
    public function foo(): string
    {
        return TestDefaults::SALES_CHANNEL;
    }
}
