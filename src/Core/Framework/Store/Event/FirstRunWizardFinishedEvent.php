<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\FrwState;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('merchant-services')]
class FirstRunWizardFinishedEvent extends Event
{
    public function __construct(
        private readonly FrwState $state,
        private readonly FrwState $previousState,
        private readonly Context $context
    ) {
    }

    public function getState(): FrwState
    {
        return $this->state;
    }

    public function getPreviousState(): FrwState
    {
        return $this->previousState;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
