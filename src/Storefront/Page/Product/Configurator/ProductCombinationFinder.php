<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Configurator;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.5.0 - Class will be removed, use \Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute instead
 * @package inventory
 */
#[Package('inventory')]
class ProductCombinationFinder
{
    private FindProductVariantRoute $findVariantRoute;

    /**
     * @internal
     */
    public function __construct(FindProductVariantRoute $findVariantRoute)
    {
        $this->findVariantRoute = $findVariantRoute;
    }

    /**
     * @throws ProductNotFoundException
     */
    public function find(string $productId, ?string $wishedGroupId, array $options, SalesChannelContext $salesChannelContext): FoundCombination
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', FindProductVariantRoute::class)
        );

        $result = $this->findVariantRoute->load(
            $productId,
            new Request(
                [
                    'switchedGroup' => $wishedGroupId,
                    'options' => $options,
                ]
            ),
            $salesChannelContext
        );

        return new FoundCombination($result->getFoundCombination()->getVariantId(), $result->getFoundCombination()->getOptions());
    }
}
