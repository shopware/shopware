<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Script\Debugging\Debug;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Script\Exception\HookAwareServiceException;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Framework\Script\Execution\Awareness\HookAwareService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Twig\Environment;
use Twig\Extension\DebugExtension;

class ScriptExecutor
{
    private LoggerInterface $logger;

    private ScriptLoader $loader;

    private ScriptTraces $traces;

    private ContainerInterface $container;

    /**
     * @psalm-suppress ContainerDependency
     */
    public function __construct(ScriptLoader $loader, LoggerInterface $logger, ScriptTraces $traces, ContainerInterface $container)
    {
        $this->logger = $logger;
        $this->loader = $loader;
        $this->traces = $traces;
        $this->container = $container;
    }

    public function execute(Hook $hook): void
    {
        if (!Feature::isActive('FEATURE_NEXT_17441')) {
            return;
        }

        $scripts = $this->loader->get($hook->getName());

        $this->traces->init($hook->getName());

        foreach ($scripts as $script) {
            try {
                $this->render($hook, $script);
            } catch (\Throwable $e) {
                $scriptException = new ScriptExecutionFailedException($hook->getName(), $script->getName(), $e);

                $this->logger->error($scriptException->getMessage(), ['exception' => $e]);

                throw $scriptException;
            }
        }
    }

    private function render(Hook $hook, Script $script): void
    {
        $twig = $this->initEnv($script);

        $twig->addGlobal('services', $this->initServices($hook));

        $this->traces->trace($hook, $script, function (Debug $debug) use ($twig, $script, $hook): void {
            $twig->addGlobal('debug', $debug);

            $twig->render($script->getName(), ['hook' => $hook]);
        });
    }

    private function initEnv(Script $script): Environment
    {
        $twig = new Environment(
            new ScriptTwigLoader($script),
            $script->getTwigOptions()
        );

        if ($script->getTwigOptions()['debug'] ?? false) {
            $twig->addExtension(new DebugExtension());
        }

        return $twig;
    }

    private function initServices(Hook $hook): array
    {
        $services = [];
        foreach ($hook->getServiceIds() as $serviceId) {
            if (!$this->container->has($serviceId)) {
                throw new ServiceNotFoundException($serviceId, 'Hook: ' . $hook->getName());
            }

            $service = $this->container->get($serviceId);
            if (!$service instanceof HookAwareService) {
                throw new HookAwareServiceException($serviceId);
            }

            $service->inject($hook);

            $services[$service->getName()] = $service;
        }

        return $services;
    }
}
