<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

/**
 * @internal only for use by the app-system, overridden for the cloud env
 */
interface ScriptFileReaderInterface
{
    /**
     * Returns the list of script paths the given app contains
     *
     * @return string[]
     */
    public function getScriptPathsForApp(string $appPath): array;

    /**
     * Returns the content of the script
     */
    public function getScriptContent(string $name, string $appPath): string;
}
