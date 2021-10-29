<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Script\Registry;

use Shopware\Core\Framework\App\Script\ExecutableScript;
use Shopware\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
class ScriptRegistry
{
    private ExecutableScriptLoaderInterface $executableScriptLoader;

    /**
     * @var array<string, ExecutableScript[]>|null
     */
    private ?array $scripts = null;

    public function __construct(ExecutableScriptLoaderInterface $executableScriptLoader)
    {
        $this->executableScriptLoader = $executableScriptLoader;
    }

    /**
     * @return ExecutableScript[]
     */
    public function getExecutableScripts(string $hook, Context $context): array
    {
        if ($this->scripts === null) {
            $this->scripts = $this->executableScriptLoader->loadExecutableScripts($context);
        }

        return $this->scripts[$hook] ?? [];
    }
}
