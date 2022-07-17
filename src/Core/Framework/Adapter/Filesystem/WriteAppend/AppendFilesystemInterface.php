<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\WriteAppend;

use League\Flysystem\FilesystemInterface;

interface AppendFilesystemInterface extends FilesystemInterface
{
    public function writeAppend(string $path, string $contents, array $config = []): bool;

    public function writeStreamAppend(string $path, $resource, array $config = []): bool;
}
