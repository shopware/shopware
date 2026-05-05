<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class StaticDocumentDataProvider extends AbstractDocumentDataProvider
{
    final public const KEY = 'fixture';

    /**
     * @param list<string> $documentTypes
     */
    public function __construct(
        private array $documentTypes = [DocumentType::INVOICE->value],
        private string $key = self::KEY,
    ) {
    }

    public function getDocumentTypes(): array
    {
        return $this->documentTypes;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $criteria->addAssociation('lineItems');
    }

    public function provideRenderingData(
        OrderEntity $order,
        DocumentGenerationRequest $generationRequest,
        Context $context,
    ): StaticRenderData {
        return new StaticRenderData();
    }
}
