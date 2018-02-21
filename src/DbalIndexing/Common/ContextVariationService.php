<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Common;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Sorting\FieldSorting;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Context\Struct\ShopContext;

class ContextVariationService
{
    /**
     * @var ShopRepository
     */
    private $shopRepository;

    public function __construct(ShopRepository $shopRepository)
    {
        $this->shopRepository = $shopRepository;
    }

    /**
     * @return ShopContext[]
     */
    public function createContexts(): array
    {
        $context = ShopContext::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('shop.is_default'));
        $shops = $this->shopRepository->search(new Criteria(), $context);

        return $shops->map(function (ShopBasicStruct $shop) {
            return ShopContext::createFromShop($shop);
        });
    }
}
