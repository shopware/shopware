<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Plugin;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Plugin\AbstractPlugin;

class AppendWrite extends AbstractPlugin
{
    public function getMethod()
    {
        return 'writeAppend';
    }

    /**
     * This plugin method add possibility to use native PHP file content append.
     * In case of usage of the non-local filesystem adapter, fallback read-concatenate-write approach will be used.
     *
     * @param string $relativeTargetPath
     * @param string|resource $source
     *
     * @return bool
     */
    public function handle(string $relativeTargetPath, $source): bool
    {
        if (!\is_resource($source) && !\is_string($source)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'writeAppend expects second parameter to be either a resource or a string, "%s" given.',
                    \gettype($source)
                )
            );
        }

        $adapter = $this->filesystem->getAdapter();

        if ($adapter instanceof Local) {
            return (bool)\file_put_contents($adapter->applyPathPrefix($relativeTargetPath), $source, FILE_APPEND);
        } else {
            $content = \is_resource($source) ? \stream_get_contents($source) : $source;
            if ($content === false) {
                return false;
            }

            return $this->fallbackWriteAppend($relativeTargetPath, $content);
        }
    }

    private function fallbackWriteAppend(string $relativeTargetPath, string $source): bool
    {
        $existingContent = $this->filesystem->read($relativeTargetPath);

        return $this->filesystem->put($relativeTargetPath, $existingContent . $source);
    }
}
