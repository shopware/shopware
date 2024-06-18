<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Extensions;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @experimental stableVersion:v6.7.0 feature:EXTENSION_SYSTEM
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

    public static function pre(string $name): string
    {
        return $name . '.pre';
    }

    public static function post(string $name): string
    {
        return $name . '.post';
    }

    /**
     * @template TExtensionType of mixed
     *
     * @param Extension<TExtensionType> $extension
     *
     * @return TExtensionType
     */
    public function publish(string $name, Extension $extension, callable $function): mixed
    {
        $this->dispatcher->dispatch($extension, self::pre($name));

        if (!$extension->isPropagationStopped()) {
            $extension->result = $function(...$extension->getParams());
        }

        $extension->resetPropagation();

        $this->dispatcher->dispatch($extension, self::post($name));

        return $extension->result();
    }
}
