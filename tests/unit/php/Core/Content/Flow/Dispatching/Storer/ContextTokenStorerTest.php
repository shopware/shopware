<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Content\Flow\Dispatching\Aware\ContextTokenAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ContextTokenStorer;
use Shopware\Core\Framework\Test\Event\TestBusinessEvent;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\ContextTokenStorer
 */
class ContextTokenStorerTest extends TestCase
{
    private ContextTokenStorer $storer;

    public function setUp(): void
    {
        $this->storer = new ContextTokenStorer();
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(CustomerLoginEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(ContextTokenAware::CONTEXT_TOKEN, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(TestBusinessEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(ContextTokenAware::CONTEXT_TOKEN, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $contextToken = 'token';

        /** @var MockObject|StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(true);

        $storable->expects(static::exactly(1))
            ->method('getStore')
            ->willReturn($contextToken);

        $storable->expects(static::exactly(1))
            ->method('setData')
            ->with(ContextTokenAware::CONTEXT_TOKEN, $contextToken);

        $this->storer->restore($storable);
    }

    public function testRestoreEmptyStored(): void
    {
        /** @var MockObject|StorableFlow $storable */
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
}
