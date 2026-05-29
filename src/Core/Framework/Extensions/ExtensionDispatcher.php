<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Extensions;

use Shopware\Core\Framework\Feature;
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

    /**
     * @deprecated tag:v6.8.0 - use Extension::onPre() instead
     */
    public static function pre(string $name): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Extension::onPre()')
        );

        return $name . '.pre';
    }

    /**
     * @deprecated tag:v6.8.0 - use Extension::onPost() instead
     */
    public static function post(string $name): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Extension::onPost()')
        );

        return $name . '.post';
    }

    /**
     * @deprecated tag:v6.8.0 - use Extension::onError() instead
     */
    public static function error(string $name): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Extension::onError()')
        );

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
        $this->dispatcher->dispatch($extension, $extension::onPre());

        if (!$extension->isPropagationStopped()) {
            try {
                $extension->result = $function(...$extension->getParams());
            } catch (\Throwable $e) {
                $extension->exception = $e;

                $extension->resetPropagation();

                $this->dispatcher->dispatch($extension, $extension::onError());

                // if the extensions want to gracefully handle the exception, they can put in a result, otherwise we rethrow the exception
                if ($extension->result === null) {
                    throw $e;
                }
            }
        }

        $extension->resetPropagation();

        $this->dispatcher->dispatch($extension, $extension::onPost());

        return $extension->result();
    }
}
