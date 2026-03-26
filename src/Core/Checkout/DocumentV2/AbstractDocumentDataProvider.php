<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
abstract class AbstractDocumentDataProvider
{
    /**
     * All document types this provider supports.
     *
     * @see DocumentType
     *
     * @return list<string> document types passed as strings
     */
    abstract public function getDocumentTypes(): array;

    /**
     * Unique key for this provider, used to retrieve its data from the @see RenderInput.
     */
    abstract public function getKey(): string;

    /**
     * Enrich order criteria with additional associations
     */
    public function enrichOrderCriteria(Criteria $criteria): void
    {
        // nothing by default
    }

    abstract public function provideRenderingData(OrderEntity $order): RenderData;
}
