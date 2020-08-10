<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductGateway implements ProductGatewayInterface
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $repository;

    public function __construct(SalesChannelRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function get(array $ids, SalesChannelContext $context): ProductCollection
    {
        $criteria = new Criteria($ids);
        $criteria->setTitle('cart::products');
        $criteria->addAssociation('cover');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('featureSet');
        $criteria->addAssociation('properties.group');

        /** @var ProductCollection $result */
        $result = $this->repository->search($criteria, $context)->getEntities();

        return $result;
    }
}
