<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('inventory')]
class ProductCloseoutFilter extends NotFilter
{
    public function __construct()
    {
        parent::__construct(self::CONNECTION_AND, [
            new EqualsFilter('product.isCloseout', true),
            new EqualsFilter('product.available', false),
        ]);
    }
}
