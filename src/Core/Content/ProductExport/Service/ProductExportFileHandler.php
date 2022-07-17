<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Service;

use League\Flysystem\FileNotFoundException;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Struct\ExportBehavior;
use Shopware\Core\Framework\Adapter\Filesystem\WriteAppend\AppendFilesystemInterface;

class ProductExportFileHandler implements ProductExportFileHandlerInterface
{
    private AppendFilesystemInterface $fileSystem;

    private string $exportDirectory;

    /**
     * @internal
     */
    public function __construct(
        AppendFilesystemInterface $fileSystem,
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

    public function writeProductExportContent(string $content, string $filePath, bool $append = false): bool
    {
        if ($this->fileSystem->has($filePath) && !$append) {
            $this->fileSystem->delete($filePath);
        }

        return $append === true
            ? $this->fileSystem->writeAppend($filePath, $content)
            : $this->fileSystem->put($filePath, $content);
    }

    public function isValidFile(string $filePath, ExportBehavior $behavior, ProductExportEntity $productExport): bool
    {
        if (!$this->fileSystem->has($filePath)) {
            return false;
        }

        return $productExport->isGenerateByCronjob() || !$this->isCacheExpired($behavior, $productExport);
    }

    public function finalizePartialProductExport(string $partialFilePath, string $finalFilePath, string $headerContent, string $footerContent): bool
    {
        if ($this->fileSystem->has($partialFilePath) && $this->fileSystem->has($finalFilePath)) {
            $this->fileSystem->delete($finalFilePath);
        }

        try {
            if ((int)$this->fileSystem->getSize($partialFilePath) === 0) {
                return false;
            }

            $isHeaderWriteSuccessful = $this->fileSystem->write($finalFilePath, $headerContent);
            $isBodyWriteSuccessful = $this->fileSystem->writeStreamAppend(
                $finalFilePath,
                $this->fileSystem->readStream($partialFilePath)
            );
            $isFooterWriteSuccessful = $this->fileSystem->writeAppend($finalFilePath, $footerContent);
            $this->fileSystem->delete($partialFilePath);

            return $isHeaderWriteSuccessful && $isBodyWriteSuccessful && $isFooterWriteSuccessful;
        } catch (FileNotFoundException $e) {
            return false;
        }
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
