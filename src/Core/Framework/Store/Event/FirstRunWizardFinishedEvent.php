<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Struct\FrwState;
use Symfony\Contracts\EventDispatcher\Event;

class FirstRunWizardFinishedEvent extends Event
{
    /**
     * @var FrwState
     */
    private $state;

    /**
     * @var FrwState
     */
    private $previousState;

    /**
     * @var Context
     */
    private $context;

    public function __construct(FrwState $state, FrwState $previousState, Context $context)
    {
        $this->state = $state;
        $this->previousState = $previousState;
        $this->context = $context;
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
