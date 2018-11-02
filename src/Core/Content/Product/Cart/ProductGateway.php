<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;

class ProductGateway implements ProductGatewayInterface
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    public function __construct(RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function get(array $ids, CheckoutContext $context): ProductCollection
    {
        /** @var ProductCollection $result */
        $result = $this->repository->read(new ReadCriteria($ids), $context->getContext());

        return $result;
    }
}
