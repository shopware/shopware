<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Gateway;

use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('buyers-experience')]
interface PromotionGatewayInterface
{
    /**
     * Gets a list of promotions for the provided criteria and
     * sales channel context.
     *
     * @deprecated tag:v6.7.0 - reason:return-type-change - Return type will be changed to PromotionCollection
     *
     * @return PromotionCollection
     */
    public function get(Criteria $criteria, SalesChannelContext $context): EntityCollection;
}
