<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderData;
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
     * @see DocumentType
     *
     * @return list<string>
     */
    abstract public function getDocumentTypes(): array;

    abstract public function getKey(): string;

    abstract public function provideRenderingData(OrderEntity $order): RenderData;

    public function enrichOrderCriteria(Criteria $criteria): void
    {
    }
}
