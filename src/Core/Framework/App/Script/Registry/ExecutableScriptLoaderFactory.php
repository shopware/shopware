<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Script\Registry;

/**
 * @internal only for use by the app-system
 */
class ExecutableScriptLoaderFactory
{
    private ExecutableScriptLoaderInterface $databaseLoader;

    private ExecutableScriptLoaderInterface $fileLoader;

    private string $appEnv;

    public function __construct(ExecutableScriptLoaderInterface $databaseLoader, ExecutableScriptLoaderInterface $fileLoader, string $appEnv)
    {
        $this->databaseLoader = $databaseLoader;
        $this->fileLoader = $fileLoader;
        $this->appEnv = $appEnv;
    }

    public function getScriptLoader(): ExecutableScriptLoaderInterface
    {
        if ($this->appEnv === 'dev') {
            return $this->fileLoader;
        }

        return $this->databaseLoader;
    }
}
