<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Framework\Log\Package;

#[Package('sales-channel')]
class ProductExportFileHandler implements ProductExportFileHandlerInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FilesystemOperator $fileSystem,
        private readonly string $exportDirectory
    ) {
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

    public function writeProductExportContent(string $content, string $filePath, bool $append = false): bool
    {
        if ($this->fileSystem->fileExists($filePath) && !$append) {
            $this->fileSystem->delete($filePath);
        }

        $existingContent = '';
        if ($append && $this->fileSystem->fileExists($filePath)) {
            $existingContent = $this->fileSystem->read($filePath);
        }

        $this->fileSystem->write(
            $filePath,
            $existingContent . $content
        );

        return true;
    }

    public function isValidFile(string $filePath, ExportBehavior $behavior, ProductExportEntity $productExport): bool
    {
        if (!$this->fileSystem->fileExists($filePath)) {
            return false;
        }

        return $productExport->isGenerateByCronjob() || !$this->isCacheExpired($behavior, $productExport);
    }

    public function finalizePartialProductExport(string $partialFilePath, string $finalFilePath, string $headerContent, string $footerContent): bool
    {
        if ($this->fileSystem->fileExists($partialFilePath) && $this->fileSystem->fileExists($finalFilePath)) {
            $this->fileSystem->delete($finalFilePath);
        }

        $content = $this->fileSystem->read($partialFilePath);

        try {
            $this->fileSystem->delete($partialFilePath);
        } catch (UnableToDeleteFile) {
            return false;
        }

        $this->fileSystem->write(
            $finalFilePath,
            $headerContent . $content . $footerContent
        );

        return true;
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
        if (!$this->fileSystem->fileExists($this->exportDirectory)) {
            $this->fileSystem->createDirectory($this->exportDirectory);
        }
    }
}
