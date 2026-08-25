<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed. Instead create own provider extending {@link AbstractDocumentDataProvider} and extend the order criteria via `enrichOrderCriteria()`.
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
final class DocumentOrderCriteriaEvent extends Event implements GenericEvent
{
    private readonly string $name;

    /**
     * @param array<string, DocumentGenerateOperation> $operations
     */
    public function __construct(
        private readonly Criteria $criteria,
        private readonly Context $context,
        private readonly array $operations,
        private readonly DocumentRendererConfig $documentRendererConfig,
        string $documentType,
    ) {
        $this->name = $documentType . '.document.criteria';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string, DocumentGenerateOperation> $operations
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getDocumentRendererConfig(): DocumentRendererConfig
    {
        return $this->documentRendererConfig;
    }
}
