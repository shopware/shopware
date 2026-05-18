<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Support;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExportV2\File\ImportExportV2FileEntity;
use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Keeps file persistence in one place so the run service can focus on run lifecycle.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class FileService
{
    /**
     * @param EntityRepository<EntityCollection<ImportExportV2FileEntity>> $fileRepository
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly EntityRepository $fileRepository
    ) {
    }

    public function createFile(string $name, string $mimeType, string $contents, Context $context): ImportExportV2FileEntity
    {
        $id = Uuid::randomHex();
        $path = 'import-export-v2/' . $id;

        $this->filesystem->write($path, $contents);

        $fileData = [
            'id' => $id,
            'name' => $name,
            'path' => $path,
            'mimeType' => $mimeType,
        ];

        $this->fileRepository->upsert([$fileData], $context);

        $file = new ImportExportV2FileEntity();
        $file->assign($fileData);

        return $file;
    }

    public function createFileFromPath(string $sourcePath, string $name, string $mimeType, Context $context): ImportExportV2FileEntity
    {
        $sourceStream = fopen($sourcePath, 'rb');
        if (!\is_resource($sourceStream)) {
            throw new \RuntimeException(\sprintf('Could not open import source file "%s".', $sourcePath));
        }

        $id = Uuid::randomHex();
        $path = 'import-export-v2/' . $id;

        try {
            $this->filesystem->writeStream($path, $sourceStream);
        } finally {
            fclose($sourceStream);
        }

        $fileData = [
            'id' => $id,
            'name' => $name,
            'path' => $path,
            'mimeType' => $mimeType,
        ];

        $this->fileRepository->upsert([$fileData], $context);

        $file = new ImportExportV2FileEntity();
        $file->assign($fileData);

        return $file;
    }

    public function getFile(string $fileId, Context $context): ?ImportExportV2FileEntity
    {
        $entity = $this->fileRepository->search(new Criteria([$fileId]), $context)->first();

        return $entity instanceof ImportExportV2FileEntity ? $entity : null;
    }

    public function readFileContents(ImportExportV2FileEntity $file): string
    {
        $path = $file->getPath();
        if ($path === null || $path === '') {
            throw ImportExportV2Exception::fileNotFound($file->getId());
        }

        return $this->filesystem->read($path);
    }

    public function saveFile(ImportExportV2FileEntity $file, Context $context): void
    {
        $path = $file->getPath();
        if ($path === null || $path === '') {
            throw ImportExportV2Exception::fileNotFound($file->getId());
        }

        // Writers may already have updated the file contents on storage, but persisting the
        // metadata again here keeps the DAL entity and the actual file location in sync.
        $this->fileRepository->upsert([[
            'id' => $file->getId(),
            'name' => $file->getName(),
            'path' => $path,
            'mimeType' => $file->getMimeType(),
        ]], $context);
    }

    public function getOrCreateLocalWorkingCopyPath(ImportExportV2FileEntity $file): string
    {
        $localPath = $this->getLocalWorkingCopyStoragePath($file);
        if (is_file($localPath)) {
            return $localPath;
        }

        $directory = \dirname($localPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Could not create import working copy directory "%s".', $directory));
        }

        $inputStream = $this->openReadStream($file);
        $localStream = fopen($localPath, 'wb');
        if (!\is_resource($localStream)) {
            fclose($inputStream);

            throw new \RuntimeException(\sprintf('Could not create import working copy "%s".', $localPath));
        }

        try {
            stream_copy_to_stream($inputStream, $localStream);
        } finally {
            fclose($inputStream);
            fclose($localStream);
        }

        return $localPath;
    }

    public function removeLocalWorkingCopy(ImportExportV2FileEntity $file): void
    {
        $localPath = $this->getLocalWorkingCopyStoragePath($file);
        if (is_file($localPath)) {
            @unlink($localPath);
        }
    }

    /**
     * @return resource
     */
    private function openReadStream(ImportExportV2FileEntity $file)
    {
        $path = $file->getPath();
        if ($path === null || $path === '') {
            throw ImportExportV2Exception::fileNotFound($file->getId());
        }

        $stream = $this->filesystem->readStream($path);
        if (!\is_resource($stream)) {
            throw ImportExportV2Exception::fileNotFound($file->getId());
        }

        return $stream;
    }

    private function getLocalWorkingCopyStoragePath(ImportExportV2FileEntity $file): string
    {
        $name = $file->getName() ?? $file->getId();
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', basename($name)) ?: $file->getId();

        return sys_get_temp_dir() . '/import-export-v2/' . $file->getId() . '-' . $safeName;
    }
}
