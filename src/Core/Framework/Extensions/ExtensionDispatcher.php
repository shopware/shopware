<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Extensions;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @experimental stableVersion:v6.7.0
 */
#[Package('core')]
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
