<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Adapter\Filesystem\FilesystemFactory;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationFilesystemFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FilesystemOperator $privateFilesystem,
        private readonly FilesystemFactory $filesystemFactory,
        private readonly string $projectDir,
        private readonly bool $useLocalFilesystem,
    ) {
    }

    public function create(): FilesystemOperator
    {
        if (!$this->useLocalFilesystem) {
            return $this->privateFilesystem;
        }

        return $this->filesystemFactory->privateFactory([
            'type' => 'local',
            'config' => ['root' => $this->projectDir . '/var'],
        ]);
    }
}
