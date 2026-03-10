<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Recurring;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * This is an experimental payment struct to make generic subscription information available without relying on a payment handler to a specific subscription extensions
 */
#[Package('checkout')]
class RecurringDataStruct extends Struct
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        protected string $subscriptionId,
        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        protected \DateTimeInterface $nextSchedule,
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public function getSubscriptionId(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        return $this->subscriptionId;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public function getNextSchedule(): \DateTimeInterface
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        return $this->nextSchedule;
    }
}
