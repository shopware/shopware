<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Test\CollectingMessageBus;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Product\LanguageSubscriber;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Product\LanguageSubscriber
 */
class LanguageSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(['sales_channel_language.written' => 'onSalesChannelWritten'], LanguageSubscriber::getSubscribedEvents());
    }

    public function testElasticsearchIsDisabledDoesNothing(): void
    {
        $subscriber = new LanguageSubscriber(
            $this->createMock(ElasticsearchHelper::class),
            $this->createMock(ProductDefinition::class),
            $this->createMock(Client::class),
            $this->createMock(MessageBusInterface::class)
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects(static::never())
            ->method('getWriteResults');

        $subscriber->onSalesChannelWritten($event);
    }

    public function testOnNewLanguageIndexGetsCreated(): void
    {
        $bus = new CollectingMessageBus();

        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('allowIndexing')->willReturn(true);

        $subscriber = new LanguageSubscriber(
            $elasticsearchHelper,
            $this->createMock(ProductDefinition::class),
            $this->createMock(Client::class),
            $bus
        );

        $writeResultCreated = new EntityWriteResult('1', ['languageId' => '1'], 'sales_channel_language', EntityWriteResult::OPERATION_INSERT);
        $writeResultUpdated = new EntityWriteResult('1', ['languageId' => '1'], 'sales_channel_language', EntityWriteResult::OPERATION_UPDATE);
        $event = new EntityWrittenEvent('sales_channel_language', [$writeResultCreated, $writeResultUpdated], Context::createDefaultContext());
        $subscriber->onSalesChannelWritten($event);

        static::assertCount(1, $bus->getMessages());
    }

    public function testOnNewLanguageIndexExistsAlready(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(static::never())->method('dispatch');

        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('allowIndexing')->willReturn(true);

        $namespace = $this->createMock(IndicesNamespace::class);
        $namespace->method('exists')->willReturn(true);

        $client = $this->createMock(Client::class);
        $client
            ->method('indices')
            ->willReturn($namespace);

        $subscriber = new LanguageSubscriber(
            $elasticsearchHelper,
            $this->createMock(ProductDefinition::class),
            $client,
            $bus
        );

        $writeResultCreated = new EntityWriteResult('1', ['languageId' => '1'], 'sales_channel_language', EntityWriteResult::OPERATION_INSERT);
        $event = new EntityWrittenEvent('sales_channel_language', [$writeResultCreated], Context::createDefaultContext());
        $subscriber->onSalesChannelWritten($event);
    }
}
