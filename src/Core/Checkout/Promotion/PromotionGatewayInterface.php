<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface PromotionGatewayInterface
{
    /**
     * Gets a list of all available promotions
     * within the current checkout context.
     */
    public function getByContext(SalesChannelContext $context): EntityCollection;

    /**
     * Gets a list of promotions that match the provided codes.
     * It also makes sure to only return active and valid promotions.
     */
    public function getByCodes(array $codes, SalesChannelContext $context): EntityCollection;
}
