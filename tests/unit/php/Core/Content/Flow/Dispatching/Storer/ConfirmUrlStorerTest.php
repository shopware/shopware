<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Content\Flow\Dispatching\Aware\ConfirmUrlAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ConfirmUrlStorer;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Shopware\Core\Framework\Feature;

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

    protected function setUp(): void
    {
        $this->storer = new ConfirmUrlStorer();
    }

    public function testStoreWithAware(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);
        $event = $this->createMock(CustomerDoubleOptInRegistrationEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(ConfirmUrlAware::CONFIRM_URL, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);
        $event = $this->createMock(TestFlowBusinessEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(ConfirmUrlAware::CONFIRM_URL, $stored);
    }

    public function testRestoreWithEmptyStored(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

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
        Feature::skipTestIfActive('v6.6.0.0', $this);

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
