<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Foo;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Tests\Unit\Administration\AdministrationTest;

class BarTest extends AdministrationTest
{
    public function testFoo(): void
    {
        $this->createMock(OrderEntity::class);
    }
}
