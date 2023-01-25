<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Aware\ReviewFormDataAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ReviewFormDataStorer;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\ReviewFormDataStorer
 */
class ReviewFormDataStorerTest extends TestCase
{
    private ReviewFormDataStorer $storer;

    public function setUp(): void
    {
        $this->storer = new ReviewFormDataStorer();
    }

    public function testStoreAware(): void
    {
        $event = new ReviewFormEvent(Context::createDefaultContext(), '', new MailRecipientStruct([]), new DataBag(), '', '');
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(ReviewFormDataAware::REVIEW_FORM_DATA, $stored);
    }

    public function testStoreNotAware(): void
    {
        $event = $this->createMock(TestFlowBusinessEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(ReviewFormDataAware::REVIEW_FORM_DATA, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $reviewFormData = ['test'];

        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(true);

        $storable->expects(static::exactly(1))
            ->method('getStore')
            ->willReturn($reviewFormData);

        $storable->expects(static::exactly(1))
            ->method('setData')
            ->with(ReviewFormDataAware::REVIEW_FORM_DATA, $reviewFormData);

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
