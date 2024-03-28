<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Foo;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class BarTest extends TestCase
{
    public function testFoo(): void
    {
        // not allowed
        $this->createMock(OrderEntity::class);

        // allowed
        $this->createMock(EntitySearchResult::class);
    }
}
