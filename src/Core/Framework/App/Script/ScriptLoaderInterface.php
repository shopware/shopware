<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Script;

interface ScriptLoaderInterface
{
    /**
     * @return string[]
     */
    public function getScriptPathsForAppPath(string $appPath): array;

    public function getScriptContent(string $name, string $appPath): string;

    public function getLastModifiedDate(string $name, string $appPath): \DateTimeInterface;
}
