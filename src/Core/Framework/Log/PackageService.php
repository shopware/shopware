<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
class PackageService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function getPackageTrace(object $subject): array
    {
        $packages = [];

        if ($controllerPackage = $this->getControllerPackage()) {
            $packages['entrypoint'] = $controllerPackage;
        }

        if ($subject instanceof \Throwable) {
            if ($package = $this->getCommandPackage($subject)) {
                $packages['entrypoint'] = $package;
            }

            if ($package = $this->getExceptionPackage($subject)) {
                $packages['exception'] = $package;
            }

            if ($package = $this->getCause($subject)) {
                $packages['causingClass'] = $package;
            }
        }

        return $packages;
    }

    public function getControllerPackage(): ?string
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
            [$controllerClass, $_] = explode('::', $controller);
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

    public function getCommandPackage(\Throwable $exception): ?string
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

    public function getExceptionPackage(\Throwable $exception): ?string
    {
        do {
            $package = Package::getPackageName($exception::class);
        } while (!$package && ($exception = $exception->getPrevious()));

        return $package;
    }

    public function getCause(\Throwable $exception): ?string
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
