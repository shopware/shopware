<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal We might break this in v6.2
 */
#[Package('services-settings')]
class DownloadService
{
    /**
     * @internal
     *
     * @param EntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly EntityRepository $fileRepository
    ) {
    }

    public function regenerateToken(Context $context, string $fileId): string
    {
        $token = ImportExportFileEntity::generateAccessToken();

        $this->fileRepository->update(
            [['id' => $fileId, 'accessToken' => $token]],
            $context
        );

        return $token;
    }

    public function createFileResponse(Context $context, string $fileId, string $accessToken): Response
    {
        $entity = $this->findFile($context, $fileId);

        $fileAccessToken = (string) $entity->getAccessToken();

        if ($fileAccessToken === '' || $entity->getAccessToken() !== $accessToken || !$this->isModifiedRecently($entity)) {
            throw ImportExportException::invalidFileAccessToken();
        }

        $this->fileRepository->update(
            [['id' => $fileId, 'accessToken' => null]],
            $context
        );

        $originalName = (string) preg_replace('/[\/\\\]/', '', $entity->getOriginalName());

        $headers = [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                'attachment',
                $originalName,
                // only printable ascii
                (string) preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $originalName)
            ),
            'Content-Length' => $this->filesystem->fileSize($entity->getPath()),
            'Content-Type' => 'application/octet-stream',
        ];
        $stream = $this->filesystem->readStream($entity->getPath());
        if (!\is_resource($stream)) {
            throw ImportExportException::fileNotFound($fileId);
        }

        return new StreamedResponse(function () use ($stream): void {
            fpassthru($stream);
        }, Response::HTTP_OK, $headers);
    }

    private function findFile(Context $context, string $fileId): ImportExportFileEntity
    {
        $entity = $this->fileRepository->search(new Criteria([$fileId]), $context)->get($fileId);

        if (!$entity instanceof ImportExportFileEntity) {
            throw ImportExportException::fileNotFound($fileId);
        }

        return $entity;
    }

    private function isModifiedRecently(ImportExportFileEntity $entity): bool
    {
        if ($entity->getUpdatedAt() === null) {
            return false;
        }

        $diff = time() - $entity->getUpdatedAt()->getTimestamp();

        return $diff < 300;
    }
}
