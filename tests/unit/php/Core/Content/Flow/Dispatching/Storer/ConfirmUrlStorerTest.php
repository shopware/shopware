<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Content\Flow\Dispatching\Aware\ConfirmUrlAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ConfirmUrlStorer;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\ConfirmUrlStorer
 */
class ConfirmUrlStorerTest extends TestCase
{
    private ConfirmUrlStorer $storer;

    public function setUp(): void
    {
        $this->storer = new ConfirmUrlStorer();
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(CustomerDoubleOptInRegistrationEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(ConfirmUrlAware::CONFIRM_URL, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(TestFlowBusinessEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(ConfirmUrlAware::CONFIRM_URL, $stored);
    }

    public function testRestoreWithEmptyStored(): void
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

    public function testRestoreWithAlreadyStored(): void
    {
        $confirmUrl = 'shopware-test.com';

        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(true);

        $storable->expects(static::exactly(1))
            ->method('getStore')
            ->willReturn($confirmUrl);

        $storable->expects(static::exactly(1))
            ->method('setData')
            ->with(ConfirmUrlAware::CONFIRM_URL, $confirmUrl);

        $this->storer->restore($storable);
    }
}
