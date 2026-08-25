<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Deprecation\BCChange\NamespaceChange;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DocumentBaseConfigSalesChannelEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[NamespaceChange(version: 'v6.9.0', newLocation: 'Shopware\\Core\\Checkout\\DocumentV2\\Aggregate\\DocumentBaseConfigSalesChannel\\DocumentBaseConfigSalesChannelCollection')]
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
