<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log\Monolog;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('core')]
class AnnotatePackageHandler extends AbstractHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly HandlerInterface $handler,
        private readonly RequestStack $requestStack,
        private readonly ContainerInterface $container,
    ) {
        parent::__construct();
    }

    public function handle(LogRecord $record): bool
    {
        $packages = [];

        $exception = $record->context['exception'] ?? null;
        if ($exception instanceof \ErrorException && str_starts_with($exception->getMessage(), 'User Deprecated:')) {
            return $this->handler->handle($record);
        }

        if ($controllerPackage = $this->getControllerPackage()) {
            $packages['entrypoint'] = $controllerPackage;
        }

        if ($exception instanceof \Throwable) {
            if ($package = $this->getCommandPackage($exception)) {
                $packages['entrypoint'] = $package;
            }

            if ($package = Package::getPackageName($exception::class)) {
                $packages['exception'] = $package;
            }

            if ($package = $this->getCause($exception)) {
                $packages['causingClass'] = $package;
            }
        }

        if ($packages !== []) {
            $context = $record->context;
            $context[Package::PACKAGE_TRACE_ATTRIBUTE_KEY] = $packages;

            $record = new LogRecord(
                $record->datetime,
                $record->channel,
                $record->level,
                $record->message,
                $context,
                $record->extra,
                $record->formatted
            );
        }

        return $this->handler->handle($record);
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

        [$controllerClass, $_] = explode('::', (string) $controller);

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
        $trace = $exception->getTrace();

        foreach ($trace as $x) {
            if (isset($x['class']) && is_subclass_of($x['class'], Command::class)) {
                $package = Package::getPackageName($x['class']);

                if ($package) {
                    return $package;
                }
            }
        }

        return null;
    }

    private function getCause(\Throwable $exception): ?string
    {
        $trace = $exception->getTrace();

        foreach ($trace as $x) {
            if (isset($x['class'])) {
                $package = Package::getPackageName($x['class']);

                if ($package) {
                    return $package;
                }
            }
        }

        return null;
    }
}
