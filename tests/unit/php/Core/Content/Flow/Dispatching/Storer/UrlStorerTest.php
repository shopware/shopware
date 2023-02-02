<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Aware\UrlAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\UrlStorer;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\UrlStorer
 */
class UrlStorerTest extends TestCase
{
    private UrlStorer $storer;

    public function setUp(): void
    {
        $this->storer = new UrlStorer();
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(NewsletterRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(UrlAware::URL, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(TestFlowBusinessEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(UrlAware::URL, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $url = 'shopware-test.com';

        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(true);

        $storable->expects(static::exactly(1))
            ->method('getStore')
            ->willReturn($url);

        $storable->expects(static::exactly(1))
            ->method('setData')
            ->with(UrlAware::URL, $url);

        $this->storer->restore($storable);
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
}
