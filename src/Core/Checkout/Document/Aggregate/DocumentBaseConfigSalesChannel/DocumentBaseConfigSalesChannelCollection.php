<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                      add(DocumentBaseConfigSalesChannelEntity $entity)
 * @method void                                      set(string $key, DocumentBaseConfigSalesChannelEntity $entity)
 * @method DocumentBaseConfigSalesChannelEntity[]    getIterator()
 * @method DocumentBaseConfigSalesChannelEntity[]    getElements()
 * @method DocumentBaseConfigSalesChannelEntity|null get(string $key)
 * @method DocumentBaseConfigSalesChannelEntity|null first()
 * @method DocumentBaseConfigSalesChannelEntity|null last()
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
