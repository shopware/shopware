<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Script\Registry;

use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Script\ExecutableScript;
use Shopware\Core\Framework\App\Script\ScriptLoaderInterface;
use Shopware\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
class ExecutableFileScriptLoader implements ExecutableScriptLoaderInterface
{
    private ActiveAppsLoader $activeAppsLoader;

    private string $projectDir;

    private ScriptLoaderInterface $scriptLoader;

    public function __construct(ActiveAppsLoader $activeAppsLoader, ScriptLoaderInterface $scriptLoader, string $projectDir)
    {
        $this->activeAppsLoader = $activeAppsLoader;
        $this->projectDir = $projectDir;
        $this->scriptLoader = $scriptLoader;
    }

    public function loadExecutableScripts(Context $context): array
    {
        $scripts = [];

        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            $appPath = $this->projectDir . '/' . $app['path'];

            foreach ($this->scriptLoader->getScriptPathsForAppPath($appPath) as $script) {
                $hook = explode('/', $script)[0];

                $scripts[$hook][] = new ExecutableScript(
                    $script,
                    $this->scriptLoader->getScriptContent($script, $appPath),
                    $this->scriptLoader->getLastModifiedDate($script, $appPath),
                    [
                        'debug' => true,
                    ]
                );
            }
        }

        return $scripts;
    }
}
