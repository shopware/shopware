<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\CrossSelling;

use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElement as CoreCrossSellingElement;

/**
 * @deprecated tag:v6.4.0 - Use `Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElement` instead
 */
class CrossSellingElement extends CoreCrossSellingElement
{
    public function getApiAlias(): string
    {
        return 'storefront_cross_selling_element';
    }
}
