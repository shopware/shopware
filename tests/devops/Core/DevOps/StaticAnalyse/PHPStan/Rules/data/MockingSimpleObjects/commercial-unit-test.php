<?php

declare(strict_types=1);

namespace Shopware\Commercial\Tests\Unit\Foo;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BarTest extends TestCase
{
    public function testFoo(): void
    {
        // not allowed
        $this->createMock(OrderEntity::class);

        // allowed
        $this->createMock(SalesChannelContext::class);
    }
}
