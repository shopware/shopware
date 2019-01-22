<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ProductGateway implements ProductGatewayInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    public function __construct(EntityRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function get(array $ids, CheckoutContext $context): ProductCollection
    {
        /** @var ProductCollection $result */
        $result = $this->repository->read(new Criteria($ids), $context->getContext());

        return $result;
    }
}
