<?php

namespace Shopware\Core\Framework\Decoration;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Decorator
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function decorate(Decoration $decoration, callable $function): mixed
    {
        $decoration->result = $this->pre($decoration, $function);

        if ($decoration->isPropagationStopped()) {
            return $decoration->result();
        }

        $this->dispatcher->dispatch($decoration, $decoration::post());

        return $decoration->result();
    }

    private function pre(Decoration $decoration, callable $core): mixed
    {
        $pre = $decoration::name() . '.pre';

        $listeners = $this->dispatcher->getListeners($pre);

        foreach ($listeners as $listener) {
            $fake = new EventDispatcher();
            $fake->addListener($pre, $listener);
            $fake->dispatch($decoration, $pre);

            if ($decoration->result() !== null) {
                return $decoration->result();
            }

            if ($decoration->isPropagationStopped()) {
                return $decoration->result();
            }
        }

        return $core($decoration);
    }
}
