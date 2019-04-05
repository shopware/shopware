<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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

    public function get(array $ids, SalesChannelContext $context): ProductCollection
    {
        /** @var ProductCollection $result */
        $result = $this->repository->search(new Criteria($ids), $context->getContext())->getEntities();

        return $result;
    }
}
