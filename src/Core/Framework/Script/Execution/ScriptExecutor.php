<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Script\Debugging\Debug;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Twig\Environment;
use Twig\Extension\DebugExtension;

class ScriptExecutor
{
    private LoggerInterface $logger;

    private ScriptLoader $loader;

    private ScriptTraces $traces;

    public function __construct(ScriptLoader $loader, LoggerInterface $logger, ScriptTraces $traces)
    {
        $this->logger = $logger;
        $this->loader = $loader;
        $this->traces = $traces;
    }

    public function execute(string $hook, array $context): void
    {
        if (!Feature::isActive('FEATURE_NEXT_17441')) {
            return;
        }

        $scripts = $this->loader->get($hook);

        $this->traces->init($hook);

        foreach ($scripts as $script) {
            try {
                $this->render($hook, $script, $context);
            } catch (\Throwable $e) {
                $scriptException = new ScriptExecutionFailedException($hook, $script->getName(), $e);

                $this->logger->error($scriptException->getMessage(), ['context' => $context, 'exception' => $e]);

                throw $scriptException;
            }
        }
    }

    private function render(string $hook, Script $script, array $context): void
    {
        $twig = $this->initEnv($script);

        $twig->addGlobal('debug', $debug = new Debug());

        $time = microtime(true);
        $twig->render($script->getName(), $context);
        $took = round(microtime(true) - $time, 3);

        $name = explode('/', $script->getName());
        $name = array_pop($name);

        $this->traces->add($hook, $name, $took, $debug);
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
}
