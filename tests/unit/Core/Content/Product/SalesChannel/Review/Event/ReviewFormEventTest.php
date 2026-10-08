<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ReviewFormEvent::class)]
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
        $product = new ProductEntity();
        $product->setId($productId);

        $event = new ReviewFormEvent($context, $salesChannelId, $mailRecipientStruct, $data, $productId, $customerId, $product);

        static::assertSame($context, $event->getContext());
        static::assertSame($salesChannelId, $event->getSalesChannelId());
        static::assertSame($mailRecipientStruct, $event->getMailStruct());
        static::assertSame($data->all(), $event->getReviewFormData());
        static::assertSame($productId, $event->getProductId());
        static::assertSame($product, $event->getProduct());
        static::assertSame($customerId, $event->getCustomerId());
    }

    public function testScalarValuesCorrectly(): void
    {
        $product = new ProductEntity();
        $product->setId('product-id');

        $event = new ReviewFormEvent(
            Context::createDefaultContext(),
            'sales-channel-id',
            new MailRecipientStruct(['foo' => 'bar']),
            new DataBag(['foo' => 'bar', 'bar' => 'baz']),
            'product-id',
            'customer-id',
            $product
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('reviewFormData', $flow->data());
        static::assertSame(['foo' => 'bar', 'bar' => 'baz'], $flow->data()['reviewFormData']);
    }

    public function testConstructorRequiresProductWhenFeatureActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Not passing $product to ' . ReviewFormEvent::class . ' is deprecated and will be required in v6.8.0.'
        ));
        new ReviewFormEvent(
            Context::createDefaultContext(),
            'sales-channel-id',
            new MailRecipientStruct(['foo' => 'bar']),
            new DataBag(),
            'product-id',
            'customer-id'
        );
    }

    public function testDescribesItsFlowContract(): void
    {
        static::assertSame(ReviewFormEvent::EVENT_NAME, (new ReviewFormEvent(
            Context::createDefaultContext(),
            'sales-channel-id',
            new MailRecipientStruct(['foo' => 'bar']),
            new DataBag(),
            'product-id',
            'customer-id',
            new ProductEntity()
        ))->getName());
        static::assertSame(['reviewFormData', 'product'], array_keys(ReviewFormEvent::getAvailableData()->toArray()));
    }
}
