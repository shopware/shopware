<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Gateway;

use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
interface PromotionGatewayInterface
{
    /**
     * Gets a list of promotions for the provided criteria and
     * sales channel context.
     */
    public function get(Criteria $criteria, SalesChannelContext $context): PromotionCollection;
}
