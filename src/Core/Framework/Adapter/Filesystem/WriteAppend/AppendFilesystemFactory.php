<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\WriteAppend;

use League\Flysystem\Filesystem;
use Shopware\Core\Framework\Adapter\Filesystem\FilesystemFactory;

class AppendFilesystemFactory extends FilesystemFactory
{
    public function factory(array $config): AppendFilesystemInterface
    {
        /** @var Filesystem $filesystem */
        $filesystem = parent::factory($config);

        return new AppendFilesystem(
            $filesystem->getAdapter(),
            $filesystem->getConfig()
        );
    }
}
