<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Gateway;

use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionGateway implements PromotionGatewayInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $promotionRepository;

    /**
     * @internal
     */
    public function __construct(EntityRepositoryInterface $promotionRepository)
    {
        $this->promotionRepository = $promotionRepository;
    }

    /**
     * Gets a list of promotions for the provided criteria and
     * sales channel context.
     *
     * @return EntityCollection<PromotionEntity>
     */
    public function get(Criteria $criteria, SalesChannelContext $context): EntityCollection
    {
        $criteria->setTitle('cart::promotion');
        $criteria->addSorting(
            new FieldSorting('priority', FieldSorting::DESCENDING)
        );

        /** @var PromotionCollection $entities */
        $entities = $this->promotionRepository->search($criteria, $context->getContext())->getEntities();

        return $entities;
    }
}
