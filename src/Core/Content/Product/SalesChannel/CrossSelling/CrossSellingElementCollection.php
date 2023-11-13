<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\CrossSelling;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<CrossSellingElement>
 */
#[Package('inventory')]
class CrossSellingElementCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'cross_selling_elements';
    }

    protected function getExpectedClass(): ?string
    {
        return CrossSellingElement::class;
    }
}
