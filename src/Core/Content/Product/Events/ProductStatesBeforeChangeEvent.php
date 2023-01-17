<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Content\Product\DataAbstractionLayer\UpdatedStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class ProductStatesBeforeChangeEvent extends Event implements ShopwareEvent
{
    /**
     * @var UpdatedStates[]
     */
    protected array $updatedStates;

    protected Context $context;

    /**
     * @param UpdatedStates[] $updatedStates
     */
    public function __construct(array $updatedStates, Context $context)
    {
        $this->updatedStates = $updatedStates;
        $this->context = $context;
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
