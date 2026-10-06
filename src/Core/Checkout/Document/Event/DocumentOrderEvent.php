<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    replacement: AbstractDocumentDataProvider::class,
    description: 'Base class of the per-type order events. Register a data provider for the affected document type instead.',
)]
abstract class DocumentOrderEvent extends Event
{
    /**
     * @param array<string, DocumentGenerateOperation> $operations
     */
    public function __construct(
        private readonly OrderCollection $orders,
        private readonly Context $context,
        private readonly array $operations = []
    ) {
    }

    /**
     * @return array<string, DocumentGenerateOperation> $operations
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrders(): OrderCollection
    {
        return $this->orders;
    }
}
