<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Executor;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Framework\Script\ExecutableScript;
use Shopware\Core\Framework\Script\Registry\ScriptRegistry;
use Twig\Environment;
use Twig\Extension\DebugExtension;

class ScriptExecutor
{
    private ScriptRegistry $scriptRegistry;

    private LoggerInterface $logger;

    public function __construct(ScriptRegistry $scriptRegistry, LoggerInterface $logger)
    {
        $this->scriptRegistry = $scriptRegistry;
        $this->logger = $logger;
    }

    public function execute(string $hook, array $scriptContext): void
    {
        if (!Feature::isActive('FEATURE_NEXT_17441')) {
            return;
        }

        $scripts = $this->scriptRegistry->get($hook);

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

    private function initEnv(ExecutableScript $script): Environment
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
