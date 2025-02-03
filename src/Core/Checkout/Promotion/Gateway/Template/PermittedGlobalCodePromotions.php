<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Gateway\Template;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('checkout')]
class PermittedGlobalCodePromotions extends MultiFilter
{
    /**
     * Gets a criteria for all permitted promotions of the provided
     * sales channel context, that do require a global code.
     *
     * @param list<string> $codes
     */
    public function __construct(
        array $codes,
        string $salesChannelId
    ) {
        $activeDateRange = new ActiveDateRange();

        parent::__construct(
            MultiFilter::CONNECTION_AND,
            [new EqualsFilter('active', true),
                new EqualsFilter('promotion.salesChannels.salesChannelId', $salesChannelId),
                $activeDateRange,
                new EqualsFilter('useCodes', true),
                new EqualsFilter('useIndividualCodes', false),
                new EqualsAnyFilter('code', $codes),
            ]
        );
    }
}
