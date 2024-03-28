<?php

namespace Shopware\Core\Framework\Extensions;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ExtensionDispatcher
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function publish(Extension $extension, callable $function): mixed
    {
        $this->dispatcher->dispatch($extension, $extension::pre());

        if (!$extension->isPropagationStopped()) {
            $extension->result = $function(...$extension->getParams());
        }

        $extension->resetPropagation();

        $this->dispatcher->dispatch($extension, $extension::post());

        return $extension->result();
    }
}
