<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\WriteAppend;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class AppendFilesystem extends Filesystem implements AppendFilesystemInterface
{
    public function writeAppend(string $path, string $content, array $config = []): bool
    {
        $adapter = $this->getAdapter();

        if ($adapter instanceof Local) {
            return (bool)\file_put_contents($adapter->applyPathPrefix($path), $content, \LOCK_EX | \FILE_APPEND);
        } else {
            return $this->fallbackWriteAppend($path, $content);
        }
    }

    public function writeStreamAppend(string $path, $resource, array $config = []): bool
    {
        $adapter = $this->getAdapter();

        if ($adapter instanceof Local) {
            return (bool)\file_put_contents($adapter->applyPathPrefix($path), $resource, \LOCK_EX | \FILE_APPEND);
        } else {
            return $this->fallbackWriteAppend($path, (string)\stream_get_contents($resource));
        }
    }

    private function fallbackWriteAppend(string $relativeTargetPath, string $content): bool
    {
        $existingContent = $this->read($relativeTargetPath);

        return $this->put($relativeTargetPath, $existingContent . $content);
    }
}
