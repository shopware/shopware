<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ProductDetail;

use Shopware\Core\Framework\Struct\Struct;

class ProductDetailPageletRequest extends Struct
{
    /**
     * @var array
     */
    protected $group;

    /**
     * @return array
     */
    public function getGroup(): array
    {
        return $this->group;
    }

    /**
     * @param array $group
     */
    public function setGroup(array $group): void
    {
        $this->group = $group;
    }
}
