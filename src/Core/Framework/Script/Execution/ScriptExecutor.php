<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\Execution\ScriptLoader;
use Shopware\Core\Framework\Script\Registry\ScriptRegistry;
use Twig\Environment;
use Twig\Extension\DebugExtension;

class ScriptExecutor
{
    private LoggerInterface $logger;
    private ScriptLoader $loader;

    public function __construct(ScriptLoader $loader, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->loader = $loader;
    }

    public function execute(string $hook, array $scriptContext): void
    {
        if (!Feature::isActive('FEATURE_NEXT_17441')) {
            return;
        }

        $scripts = $this->loader->get($hook);

        foreach ($scripts as $script) {
            $twig = $this->initEnv($script);

            try {
                $twig->render($script->getName(), $scriptContext);
            } catch (\Throwable $e) {
                $scriptException = new ScriptExecutionFailedException(
                    $hook,
                    $script->getName(),
                    $e
                );
                $this->logger->error($scriptException->getMessage(), [
                    'context' => $scriptContext,
                    'exception' => $e,
                ]);

                throw $scriptException;
            }
        }
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
