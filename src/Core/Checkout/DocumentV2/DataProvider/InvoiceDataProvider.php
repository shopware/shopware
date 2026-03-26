<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\DataProvider;

use Shopware\Core\Checkout\DocumentV2\AbstractDocumentDataProvider;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Struct\InvoiceRenderData;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class InvoiceDataProvider extends AbstractDocumentDataProvider
{
    public const KEY = 'invoice';

    public function getDocumentTypes(): array
    {
        return [
            DocumentType::Invoice->value,
        ];
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $criteria->addAssociation('lineItems');
    }

    public function provideRenderingData(OrderEntity $order): InvoiceRenderData
    {
        // build up invoice data + configuration
        return new InvoiceRenderData(false);
    }
}
