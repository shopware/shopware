<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentBaseConfigSalesChannelEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class DocumentBaseConfigSalesChannelCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'document_base_config_sales_channel_collection';
    }

    protected function getExpectedClass(): string
    {
        return DocumentBaseConfigSalesChannelEntity::class;
    }
}

/** @deprecated tag:v6.9.0 - compatibility alias */
class_alias(DocumentBaseConfigSalesChannelCollection::class, 'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection');
