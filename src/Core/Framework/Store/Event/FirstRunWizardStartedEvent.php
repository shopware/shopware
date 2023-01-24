<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Struct\FrwState;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package merchant-services
 *
 * @internal
 */
class FirstRunWizardStartedEvent extends Event
{
    public function __construct(private readonly FrwState $state, private readonly Context $context)
    {
    }

    public function getState(): FrwState
    {
        return $this->state;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
