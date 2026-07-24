<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\OrderVersionStrategy;
use Shopware\Core\Checkout\DocumentV2\Struct\ProviderInput;
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
        private OrderVersionStrategy $orderVersionStrategy = OrderVersionStrategy::CREATE,
    ) {
    }

    public function supports(string $documentType): bool
    {
        return \in_array($documentType, $this->documentTypes, true);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getOrderVersionStrategy(): OrderVersionStrategy
    {
        return $this->orderVersionStrategy;
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $criteria->addAssociation('lineItems');
    }

    public function provideRenderingData(
        ProviderInput $input,
        Context $context,
    ): StaticRenderData {
        return new StaticRenderData();
    }
}
