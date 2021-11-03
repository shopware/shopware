<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Registry;

use Shopware\Core\Framework\Script\ExecutableScript;

/**
 * @internal only for use by the app-system
 */
class ScriptRegistry
{
    private ExecutableScriptLoader $executableScriptLoader;

    /**
     * @var array<string, ExecutableScript[]>|null
     */
    private ?array $scripts = null;

    public function __construct(ExecutableScriptLoader $executableScriptLoader)
    {
        $this->executableScriptLoader = $executableScriptLoader;
    }

    /**
     * @return ExecutableScript[]
     */
    public function get(string $hook): array
    {
        if ($this->scripts === null) {
            $this->scripts = $this->executableScriptLoader->loadExecutableScripts();
        }

        return $this->scripts[$hook] ?? [];
    }
}
