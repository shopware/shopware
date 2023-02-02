<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Content\Flow\Dispatching\Aware\ShopNameAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ShopNameStorer;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\ShopNameStorer
 */
class ShopNameStorerTest extends TestCase
{
    private ShopNameStorer $storer;

    public function setUp(): void
    {
        $this->storer = new ShopNameStorer();
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(CustomerAccountRecoverRequestEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(ShopNameAware::SHOP_NAME, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(TestFlowBusinessEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(ShopNameAware::SHOP_NAME, $stored);
    }

    public function testRestoreEmptyStored(): void
    {
        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(false);

        $storable->expects(static::never())
            ->method('getStore');

        $storable->expects(static::never())
            ->method('setData');

        $this->storer->restore($storable);
    }

    public function testRestoreHasStored(): void
    {
        $shopName = 'tiki';

        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(true);

        $storable->expects(static::exactly(1))
            ->method('getStore')
            ->willReturn($shopName);

        $storable->expects(static::exactly(1))
            ->method('setData')
            ->with(ShopNameAware::SHOP_NAME, $shopName);

        $this->storer->restore($storable);
    }

    public function storableProvider(): \Generator
    {
        yield 'Store key exists' => [
            true,
        ];

        yield 'Store key non exists' => [
            false,
        ];
    }

    public function awareProvider(): \Generator
    {
        $event = $this->createMock(CustomerAccountRecoverRequestEvent::class);
        yield 'Store with Aware' => [
            $event,
            true,
        ];

        $event = $this->createMock(TestFlowBusinessEvent::class);
        yield 'Store with not Aware' => [
            $event,
            false,
        ];
    }
}
