<?php declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow\DataProvider;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<ProductEntity, ProductCollection>
 */
#[Package('after-sales')]
class ProductProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}
