<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log\Monolog;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('core')]
class AnnotatePackageProcessor implements ProcessorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(LogRecord $record)
    {
        $packages = [];

        $exception = $record->context['exception'] ?? null;
        if ($exception instanceof \ErrorException && str_starts_with($exception->getMessage(), 'User Deprecated:')) {
            return $record;
        }

        if ($controllerPackage = $this->getControllerPackage()) {
            $packages['entrypoint'] = $controllerPackage;
        }

        if ($exception instanceof \Throwable) {
            if ($package = $this->getCommandPackage($exception)) {
                $packages['entrypoint'] = $package;
            }

            if ($package = $this->getExceptionPackage($exception)) {
                $packages['exception'] = $package;
            }

            if ($package = $this->getCause($exception)) {
                $packages['causingClass'] = $package;
            }
        }

        if ($packages !== []) {
            $record->extra[Package::PACKAGE_TRACE_ATTRIBUTE_KEY] = $packages;
        }

        return $record;
    }

    private function getControllerPackage(): ?string
    {
        $request = $this->requestStack->getMainRequest();
        if (!$request) {
            return null;
        }
        $controller = $request->attributes->get('_controller');
        if (!$controller) {
            return null;
        }

        if (\is_string($controller)) {
            [$controllerClass, $_] = explode('::', (string) $controller);
        } elseif (\is_array($controller) && \count($controller) === 2) {
            [$controllerClass, $_] = $controller;
        } else {
            return null;
        }

        $package = Package::getPackageName($controllerClass, true);
        if ($package) {
            return $package;
        }

        $controller = $this->container->get($controllerClass, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        if (!$controller) {
            return null;
        }

        return Package::getPackageName($controller::class, true);
    }

    private function getCommandPackage(\Throwable $exception): ?string
    {
        while ($exception && ($trace = $exception->getTrace())) {
            foreach ($trace as $x) {
                if (isset($x['class']) && is_subclass_of($x['class'], Command::class)) {
                    $package = Package::getPackageName($x['class']);

                    if ($package) {
                        return $package;
                    }
                }
            }
            $exception = $exception->getPrevious();
        }

        return null;
    }

    private function getExceptionPackage(\Throwable $exception): ?string
    {
        do {
            $package = Package::getPackageName($exception::class);
        } while (!$package && ($exception = $exception->getPrevious()));

        return $package;
    }

    private function getCause(\Throwable $exception): ?string
    {
        while ($exception && ($trace = $exception->getTrace())) {
            foreach ($trace as $x) {
                if (isset($x['class'])) {
                    $package = Package::getPackageName($x['class']);

                    if ($package) {
                        return $package;
                    }
                }
            }

            $exception = $exception->getPrevious();
        }

        return null;
    }
}
