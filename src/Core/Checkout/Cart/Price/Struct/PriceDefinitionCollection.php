<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Struct\Collection;

class PriceDefinitionCollection extends Collection
{
    /**
     * @var PriceDefinitionInterface[]
     */
    protected $elements = [];

    public function add(PriceDefinitionInterface $price): void
    {
        parent::doAdd($price);
    }

    public function remove(int $key): void
    {
        parent::doRemoveByKey($key);
    }

    public function get(int $key): ? PriceDefinitionInterface
    {
        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }
}
