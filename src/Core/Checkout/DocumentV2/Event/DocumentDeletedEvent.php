<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Event;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
class DocumentDeletedEvent extends Event implements FlowEventAware, ScalarValuesAware
{
    final public const EVENT_NAME = 'document.generation.deleted';

    public function __construct(
        public readonly string $documentId,
        public readonly string $orderId,
        public readonly string $orderVersionId,
        public readonly string $documentNumber,
        public readonly string $deletedAt,
        public readonly Context $context,
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('documentId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('documentNumber', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('deletedAt', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add(OrderAware::ORDER_ID, new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('orderVersionId', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getValues(): array
    {
        return [
            'documentId' => $this->documentId,
            'documentNumber' => $this->documentNumber,
            'deletedAt' => $this->deletedAt,
            OrderAware::ORDER_ID => $this->orderId,
            'orderVersionId' => $this->orderVersionId,
        ];
    }
}
