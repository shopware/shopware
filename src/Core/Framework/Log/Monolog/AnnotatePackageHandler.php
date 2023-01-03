<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log\Monolog;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('core')]
class AnnotatePackageHandler extends AbstractHandler
{
    private HandlerInterface $handler;

    private RequestStack $requestStack;

    /**
     * @internal
     */
    public function __construct(HandlerInterface $handler, RequestStack $requestStack)
    {
        parent::__construct();
        $this->handler = $handler;
        $this->requestStack = $requestStack;
    }

    public function handle(array $record): bool
    {
        $packages = [];

        $exception = $record['context']['exception'] ?? null;
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

            if ($package = Package::getPackageName(\get_class($exception))) {
                $packages['exception'] = $package;
            }

            if ($package = $this->getCause($exception)) {
                $packages['causingClass'] = $package;
            }
        }

        if ($packages !== []) {
            $record['context'][Package::PACKAGE_TRACE_ATTRIBUTE_KEY] = $packages;
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

        [$controllerClass, $_] = explode('::', $controller);

        $package = Package::getPackageName($controllerClass);
        // try parent class if no package attribute was found
        return $package ?? Package::getPackageName(get_parent_class($controllerClass) ?: '');
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
