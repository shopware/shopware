<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Struct\FrwState;
use Symfony\Contracts\EventDispatcher\Event;

class FirstRunWizardStartedEvent extends Event
{
    /**
     * @var FrwState
     */
    private $state;

    /**
     * @var Context
     */
    private $context;

    public function __construct(FrwState $state, Context $context)
    {
        $this->state = $state;
        $this->context = $context;
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
