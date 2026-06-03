<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Event\DocumentGeneratedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentGeneratedEvent::class)]
class DocumentGeneratedEventTest extends TestCase
{
    public function testWebhookPayloadContractExposesScalarDocumentDataWithoutDownloadSecret(): void
    {
        static::assertSame([
            'documentId' => ['type' => 'string'],
            'orderId' => ['type' => 'string'],
            'documentTypeId' => ['type' => 'string'],
            'documentNumber' => ['type' => 'string'],
        ], DocumentGeneratedEvent::getAvailableData()->toArray());
    }

    public function testFlowStorersReceiveOrderIdAndScalarValues(): void
    {
        $documentId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $documentTypeId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new DocumentGeneratedEvent($context, $documentId, $orderId, $documentTypeId, '1000');

        static::assertSame(DocumentGeneratedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($documentId, $event->getDocumentId());
        static::assertSame($orderId, $event->getOrderId());
        static::assertSame($documentTypeId, $event->getDocumentTypeId());
        static::assertSame('1000', $event->getDocumentNumber());
        static::assertSame([
            'documentId' => $documentId,
            'orderId' => $orderId,
            'documentTypeId' => $documentTypeId,
            'documentNumber' => '1000',
        ], $event->getValues());
    }

    public function testDocumentNumberIsOptional(): void
    {
        $event = new DocumentGeneratedEvent(
            Context::createDefaultContext(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex()
        );

        static::assertNull($event->getDocumentNumber());
        static::assertNull($event->getValues()['documentNumber']);
    }
}
