<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Content\Product\DataAbstractionLayer\UpdatedStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

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
     * @return UpdatedStates[]
     */
    public function getUpdatedStates(): array
    {
        return $this->updatedStates;
    }

    /**
     * @param UpdatedStates[] $updatedStates
     */
    public function setUpdatedStates(array $updatedStates): void
    {
        $this->updatedStates = $updatedStates;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
