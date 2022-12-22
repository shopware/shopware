<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Aware\ResetUrlAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ResetUrlStorer;
use Shopware\Core\Framework\Test\Event\TestBusinessEvent;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\ResetUrlStorer
 */
class ResetUrlStorerTest extends TestCase
{
    private ResetUrlStorer $storer;

    public function setUp(): void
    {
        $this->storer = new ResetUrlStorer();
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(UserRecoveryRequestEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(ResetUrlAware::RESET_URL, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(TestBusinessEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(ResetUrlAware::RESET_URL, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $resetUrl = 'shopware-test.com/reset';

        /** @var MockObject|StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(true);

        $storable->expects(static::exactly(1))
            ->method('getStore')
            ->willReturn($resetUrl);

        $storable->expects(static::exactly(1))
            ->method('setData')
            ->with(ResetUrlAware::RESET_URL, $resetUrl);

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
