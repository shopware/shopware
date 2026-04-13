<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Content\Product\DataAbstractionLayer\UpdatedStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.8.0 - Will be removed, as product states are deprecated.
 */
#[Package('inventory')]
class ProductStatesBeforeChangeEvent extends Event implements ShopwareEvent
{
    /**
     * @param UpdatedStates[] $updatedStates
     */
    public function __construct(
        protected array $updatedStates,
        protected Context $context
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, as product states are deprecated.
     *
     * @return UpdatedStates[]
     */
    public function getUpdatedStates(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getUpdatedStates', 'v6.8.0.0')
        );

        return $this->updatedStates;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, as product states are deprecated.
     *
     * @param UpdatedStates[] $updatedStates
     */
    public function setUpdatedStates(array $updatedStates): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'setUpdatedStates', 'v6.8.0.0')
        );

        $this->updatedStates = $updatedStates;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, as product states are deprecated.
     */
    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getContext', 'v6.8.0.0')
        );

        return $this->context;
    }
}
