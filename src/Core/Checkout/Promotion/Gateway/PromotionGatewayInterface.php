<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Gateway;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface PromotionGatewayInterface
{
    /**
     * Gets a list of promotions for the provided criteria and
     * sales channel context.
     */
    public function get(Criteria $criteria, SalesChannelContext $context): EntityCollection;
}
