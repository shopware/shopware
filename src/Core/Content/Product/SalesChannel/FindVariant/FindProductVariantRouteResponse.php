<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\FindVariant;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<FoundCombination>
 */
#[Package('inventory')]
class FindProductVariantRouteResponse extends StoreApiResponse
{
    public function getFoundCombination(): FoundCombination
    {
        return $this->object;
    }
}
