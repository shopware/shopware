<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\Content\Product\Params\ListingPriceParams;
use Shopware\Core\Content\Product\Params\StorePriceParams;
use Shopware\Core\Content\Product\Params\CheckoutPriceParams;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Service\ResetInterface;

#[Package('inventory')]
abstract class AbstractProductPriceCalculator implements ResetInterface
{
    public function reset(): void
    {
        $this->getDecorated()->reset();
    }

    abstract public function getDecorated(): AbstractProductPriceCalculator;

    /**
     * @param Entity[] $products
     */
    abstract public function calculate(iterable $products, SalesChannelContext $context): void;

    public function checkout(CheckoutPriceParams $params)
    {
        return $this->getDecorated()->checkout($params);
    }

    public function listing(ListingPriceParams $params)
    {
        return $this->getDecorated()->listing($params);
    }

    public function detail(StorePriceParams $params)
    {
        return $this->getDecorated()->detail($params);
    }
}
