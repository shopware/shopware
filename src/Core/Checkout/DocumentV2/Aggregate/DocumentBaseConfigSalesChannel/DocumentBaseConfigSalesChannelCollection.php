<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Deprecation\BCChange\ClassMoved;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentBaseConfigSalesChannelEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[ClassMoved(version: 'v6.9.0', previousClassName: 'Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection')]
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
