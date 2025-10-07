<?php

declare(strict_types=1);

use Shopware\Core\Defaults;

class ProductionCodeValid
{
    public function foo(): string
    {
        return Defaults::LIVE_VERSION;
    }
}
