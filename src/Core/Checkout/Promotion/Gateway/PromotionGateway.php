<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Gateway;

use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class PromotionGateway implements PromotionGatewayInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $promotionRepository)
    {
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
