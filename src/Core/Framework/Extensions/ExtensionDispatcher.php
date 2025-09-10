<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Extensions;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('framework')]
final readonly class ExtensionDispatcher
{
    /**
     * @internal
     */
    public function __construct(
        private EventDispatcherInterface $dispatcher
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

    public static function error(string $name): string
    {
        return $name . '.error';
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
            try {
                $extension->result = $function(...$extension->getParams());
            } catch (\Throwable $e) {
                $extension->exception = $e;

                $extension->resetPropagation();

                $this->dispatcher->dispatch($extension, self::error($name));

                // if the extensions want to gracefully handle the exception, they can put in a result, otherwise we rethrow the exception
                if ($extension->result === null) {
                    throw $e;
                }
            }
        }

        $extension->resetPropagation();

        $this->dispatcher->dispatch($extension, self::post($name));

        return $extension->result();
    }
}
