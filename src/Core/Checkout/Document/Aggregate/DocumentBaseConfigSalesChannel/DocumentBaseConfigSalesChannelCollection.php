<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package customer-order
 *
 * @extends EntityCollection<DocumentBaseConfigSalesChannelEntity>
 */
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
