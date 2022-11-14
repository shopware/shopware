<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Aware\SubjectAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\SubjectStorer;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Framework\Test\Event\TestBusinessEvent;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\SubjectStorer
 */
class SubjectStorerTest extends TestCase
{
    private SubjectStorer $storer;

    public function setUp(): void
    {
        $this->storer = new SubjectStorer();
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(MailSentEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(SubjectAware::SUBJECT, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(TestBusinessEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(SubjectAware::SUBJECT, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $subject = 'subject';

        /** @var MockObject|StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(true);

        $storable->expects(static::exactly(1))
            ->method('getStore')
            ->willReturn($subject);

        $storable->expects(static::exactly(1))
            ->method('setData')
            ->with(SubjectAware::SUBJECT, $subject);

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
