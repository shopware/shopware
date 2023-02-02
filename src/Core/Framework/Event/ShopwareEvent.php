<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Context;

interface ShopwareEvent
{
    public function getContext(): Context;
}
