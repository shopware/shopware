<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Gateway\Template;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('checkout')]
class PermittedAutomaticPromotions extends MultiFilter
{
    /**
     * Gets a criteria for all permitted promotions of the provided
     * sales channel context, that do get applied automatically without a code
     * if the preconditions and rules are valid.
     */
    public function __construct(string $salesChannelId)
    {
        $activeDateRange = new ActiveDateRange();

        // add conditional OR filter to either get an entry that matches any existing rule,
        // or promotions that don't have ANY rules and thus are used globally
        parent::__construct(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('active', true),
                new EqualsFilter('promotion.salesChannels.salesChannelId', $salesChannelId),
                $activeDateRange,
                new EqualsFilter('useCodes', false),
            ]
        );
    }
}
