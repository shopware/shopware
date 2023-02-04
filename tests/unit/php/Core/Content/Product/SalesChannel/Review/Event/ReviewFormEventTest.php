<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent
 */
class ReviewFormEventTest extends TestCase
{
    public function testInstance(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = 'foo';
        $mailRecipientStruct = new MailRecipientStruct(['foo' => 'bar']);
        $data = new DataBag(['baz']);
        $productId = 'bar';
        $customerId = 'bar';

        $event = new ReviewFormEvent($context, $salesChannelId, $mailRecipientStruct, $data, $productId, $customerId);

        static::assertEquals($context, $event->getContext());
        static::assertEquals($salesChannelId, $event->getSalesChannelId());
        static::assertEquals($mailRecipientStruct, $event->getMailStruct());
        static::assertEquals($data->all(), $event->getReviewFormData());
        static::assertEquals($productId, $event->getProductId());
        static::assertEquals($customerId, $event->getCustomerId());
    }
}
