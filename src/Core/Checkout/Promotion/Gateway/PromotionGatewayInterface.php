<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Gateway;

use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
interface PromotionGatewayInterface
{
    /**
     * Gets a list of promotions for the provided criteria and
     * sales channel context.
     *
     * @return EntityCollection<PromotionEntity>
     */
    public function get(Criteria $criteria, SalesChannelContext $context): EntityCollection;
}
