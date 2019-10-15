<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Content\ProductExport\Struct\ProductExportResult;

class ProductExportFileHandler implements ProductExportFileHandlerInterface
{
    /** @var FilesystemInterface */
    private $fileSystem;

    /** @var string */
    private $exportDirectory;

    public function __construct(
        FilesystemInterface $fileSystem,
        string $exportDirectory
    ) {
        $this->fileSystem = $fileSystem;
        $this->exportDirectory = $exportDirectory;
    }

    public function getFilePath(ProductExportEntity $productExport, bool $partialGeneration = false): string
    {
        $this->ensureDirectoryExists();

        $filePath = sprintf(
            '%s/%s',
            $this->exportDirectory,
            $productExport->getFileName()
        );

        if ($partialGeneration) {
            $filePath .= '.partial';
        }

        return $filePath;
    }

    public function writeProductExportResult(ProductExportResult $productExportResult, string $filePath, bool $append = false): bool {
        if ($this->fileSystem->has($filePath) && !$append) {
            $this->fileSystem->delete($filePath);
        }

        $existingContent = "";
        if ($append && $this->fileSystem->has($filePath)) {
            $existingContent = $this->fileSystem->read($filePath);
        }

        return $this->fileSystem->write(
            $filePath,
            $existingContent . $productExportResult->getContent()
        );
    }

    public function isValidFile(string $filePath, ExportBehavior $behavior, ProductExportEntity $productExport): bool
    {
        if (!$this->fileSystem->has($filePath)) {
            return false;
        }

        return $productExport->isGenerateByCronjob()
            || (!$productExport->isGenerateByCronjob() && !$this->isCacheExpired($behavior, $productExport));
    }

    private function isCacheExpired(ExportBehavior $behavior, ProductExportEntity $productExport): bool
    {
        if ($behavior->ignoreCache() || $productExport->getGeneratedAt() === null) {
            return true;
        }

        $expireTimestamp = $productExport->getGeneratedAt()->getTimestamp() + $productExport->getInterval();

        return (new \DateTime())->getTimestamp() > $expireTimestamp;
    }

    private function ensureDirectoryExists(): void
    {
        if (!$this->fileSystem->has($this->exportDirectory)) {
            $this->fileSystem->createDir($this->exportDirectory);
        }
    }
}
