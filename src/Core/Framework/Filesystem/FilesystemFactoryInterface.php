<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Filesystem;

use League\Flysystem\FilesystemInterface;

interface FilesystemFactoryInterface
{
    public function factory(array $config): FilesystemInterface;
}
