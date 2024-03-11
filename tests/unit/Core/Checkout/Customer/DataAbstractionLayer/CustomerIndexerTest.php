<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexer;
use Shopware\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexingMessage;
use Shopware\Core\Checkout\Customer\Event\CustomerIndexerEvent;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package checkout
 *
 * @internal
 */
#[CoversClass(CustomerIndexer::class)]
class CustomerIndexerTest extends TestCase
{
    public function testUpdate(): void
    {
        $customerId = Uuid::randomHex();

        $event = $this->createMock(EntityWrittenContainerEvent::class);

        $event->method('getPrimaryKeys')->willReturn(['customer']);
        $event->expects(static::once())->method('getPrimaryKeysWithPropertyChange')->willReturn([
            $customerId,
        ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $indexer = new CustomerIndexer(
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ManyToManyIdFieldUpdater::class),
            $this->createMock(CustomerNewsletterSalesChannelsUpdater::class),
            $eventDispatcher
        );

        /** @var CustomerIndexingMessage $indexing */
        $indexing = $indexer->update($event);

        static::assertSame($indexing->getIds(), [$customerId]);
    }

    public function testHandle(): void
    {
        $customerId = Uuid::randomHex();

        $message = $this->createMock(CustomerIndexingMessage::class);
        $message->method('getData')->willReturn([$customerId]);
        $message->method('getContext')->willReturn(
            $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock()
        );
        $message->method('getIds')->willReturn([$customerId]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(static::once())->method('dispatch')->willReturnCallback(function ($message) use ($customerId) {
            static::assertInstanceOf(CustomerIndexerEvent::class, $message);
            static::assertSame($message->getIds(), [$customerId]);

            return $message;
        });

        $indexer = new CustomerIndexer(
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ManyToManyIdFieldUpdater::class),
            $this->createMock(CustomerNewsletterSalesChannelsUpdater::class),
            $eventDispatcher
        );

        $indexer->handle($message);
    }
}
