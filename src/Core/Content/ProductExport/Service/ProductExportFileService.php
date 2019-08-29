<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ProductExport\ProductExportEntity;

class ProductExportFileService implements ProductExportFileServiceInterface
{
    /** @var FilesystemInterface */
    private $fileSystem;

    /** @var string */
    private $directoryName;

    public function __construct(FilesystemInterface $fileSystem, string $directoryName)
    {
        $this->fileSystem = $fileSystem;
        $this->directoryName = $directoryName;
    }

    public function getFilePath(ProductExportEntity $productExportEntity): string
    {
        return $this->getDirectory() . sprintf(
                '/%s.%s',
                $productExportEntity->getFileName(),
                $productExportEntity->getFileFormat()
            );
    }

    public function getDirectory(): string
    {
        if (!$this->fileSystem->has($this->directoryName)) {
            $this->fileSystem->createDir($this->directoryName);
        }

        return $this->directoryName;
    }
}
