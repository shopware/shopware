<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class StaticDocumentDataProvider extends AbstractDocumentDataProvider
{
    final public const KEY = 'fixture';

    /**
     * @param list<string> $documentTypes
     */
    public function __construct(
        private readonly array $documentTypes = [DocumentType::INVOICE->value],
    ) {
    }

    public function getDocumentTypes(): array
    {
        return $this->documentTypes;
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $criteria->addAssociation('lineItems');
    }

    public function provideRenderingData(OrderEntity $order): StaticRenderData
    {
        return new StaticRenderData();
    }
}
