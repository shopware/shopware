<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Event\DocumentDeletedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentDeletedEvent::class)]
class DocumentDeletedEventTest extends TestCase
{
    public function testWebhookPayloadContractExposesDeletionSnapshot(): void
    {
        static::assertSame([
            'documentId' => ['type' => 'string'],
            'orderId' => ['type' => 'string'],
            'documentNumber' => ['type' => 'string'],
            'deletedAt' => ['type' => 'string'],
        ], DocumentDeletedEvent::getAvailableData()->toArray());
    }

    public function testDeletedDocumentIsNotEntityAwareSoFlowsCannotLoadRemovedRows(): void
    {
        $documentId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new DocumentDeletedEvent($context, $documentId, $orderId, '2024-01-01T00:00:00+00:00', '1000');

        static::assertSame(DocumentDeletedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($documentId, $event->getDocumentId());
        static::assertSame($orderId, $event->getOrderId());
        static::assertSame('2024-01-01T00:00:00+00:00', $event->getDeletedAt());
        static::assertSame('1000', $event->getDocumentNumber());
        static::assertSame([
            'documentId' => $documentId,
            'orderId' => $orderId,
            'documentNumber' => '1000',
            'deletedAt' => '2024-01-01T00:00:00+00:00',
        ], $event->getValues());
    }
}
