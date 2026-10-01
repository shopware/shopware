<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem\Adapter;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface FilesystemOperatorFactoryInterface extends AdapterFactoryInterface
{
    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $filesystemOptions
     */
    public function createFilesystem(array $config, array $filesystemOptions): FilesystemOperator;
}
