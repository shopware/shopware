<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel;

use Shopware\Core\Framework\Log\Package;

class_exists(\Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection::class);

if (!class_exists(DocumentBaseConfigSalesChannelCollection::class, false)) {
    /**
     * @deprecated tag:v6.9.0 - compatibility alias, this file is deleted together with document generation v1.
     * Use \Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection instead.
     */
    #[Package('after-sales')]
    class DocumentBaseConfigSalesChannelCollection extends \Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection
    {
    }
}
