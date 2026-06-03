<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlTemplate;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateChangeSubscriber;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlTemplateChangeSubscriber::class)]
class SeoUrlTemplateChangeSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            ['seo_url_template.written' => 'onSeoUrlTemplateWritten'],
            SeoUrlTemplateChangeSubscriber::getSubscribedEvents()
        );
    }

    public function testDispatchesIndexingMessageWhenTemplateChanged(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['routeName' => 'frontend.navigation.page', 'entityName' => 'category'],
        ]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn (SeoUrlTemplateIndexingMessage $message): bool => $message->routeName === 'frontend.navigation.page'
                && $message->entityName === 'category'))
            ->willReturn(new Envelope(new \stdClass()));

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);
        $subscriber->onSeoUrlTemplateWritten($this->createEvent([
            $this->writeResult(Uuid::randomHex(), ['template' => 'custom-prefix/{{ category.name }}']),
        ]));
    }

    public function testDispatchesOneMessagePerResolvedRoute(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['routeName' => 'frontend.navigation.page', 'entityName' => 'category'],
            ['routeName' => 'frontend.detail.page', 'entityName' => 'product'],
        ]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);
        $subscriber->onSeoUrlTemplateWritten($this->createEvent([
            $this->writeResult(Uuid::randomHex(), ['template' => 'a']),
            $this->writeResult(Uuid::randomHex(), ['template' => 'b']),
        ]));
    }

    public function testIgnoresWritesWithoutTemplatePayload(): void
    {
        // Partial writes such as custom-fields-only saves must not trigger the
        // expensive reindexing pass.
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);
        $subscriber->onSeoUrlTemplateWritten($this->createEvent([
            $this->writeResult(Uuid::randomHex(), ['customFields' => ['foo' => 'bar']]),
        ]));
    }

    public function testIgnoresWriteResultsWithNonStringPrimaryKey(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);
        $subscriber->onSeoUrlTemplateWritten($this->createEvent([
            $this->writeResult(['id' => Uuid::randomHex()], ['template' => 'x']),
        ]));
    }

    public function testIgnoresRoutesWithEmptyRouteOrEntityName(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['routeName' => '', 'entityName' => 'category'],
            ['routeName' => 'frontend.navigation.page', 'entityName' => ''],
        ]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new SeoUrlTemplateChangeSubscriber($connection, $messageBus);
        $subscriber->onSeoUrlTemplateWritten($this->createEvent([
            $this->writeResult(Uuid::randomHex(), ['template' => 'x']),
        ]));
    }

    /**
     * @param list<EntityWriteResult<string|array<string, string>>> $writeResults
     */
    private function createEvent(array $writeResults): EntityWrittenEvent
    {
        return new EntityWrittenEvent('seo_url_template', $writeResults, Context::createDefaultContext());
    }

    /**
     * @param array<string, string>|string $primaryKey
     * @param array<string, mixed> $payload
     *
     * @return EntityWriteResult<string|array<string, string>>
     */
    private function writeResult(array|string $primaryKey, array $payload): EntityWriteResult
    {
        return new EntityWriteResult($primaryKey, $payload, 'seo_url_template', EntityWriteResult::OPERATION_UPDATE);
    }
}
