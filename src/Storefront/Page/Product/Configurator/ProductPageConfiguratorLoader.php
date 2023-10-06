<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Configurator;

use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('storefront')]
class ProductPageConfiguratorLoader extends ProductConfiguratorLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly ProductConfiguratorLoader $loader)
    {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function load(SalesChannelProductEntity $product, SalesChannelContext $context): PropertyGroupCollection
    {
        return $this->loader->load($product, $context);
    }
}
