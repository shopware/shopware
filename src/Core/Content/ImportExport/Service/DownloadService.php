<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\Exception\FileNotFoundException;
use Shopware\Core\Content\ImportExport\Exception\InvalidFileAccessTokenException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal We might break this in v6.2
 */
class DownloadService
{
    private FilesystemInterface $filesystem;

    private EntityRepositoryInterface $fileRepository;

    public function __construct(FilesystemInterface $filesystem, EntityRepositoryInterface $fileRepository)
    {
        $this->filesystem = $filesystem;
        $this->fileRepository = $fileRepository;
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
            throw new InvalidFileAccessTokenException();
        }

        $this->fileRepository->update(
            [['id' => $fileId, 'accessToken' => null]],
            $context
        );

        $headers = [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                'attachment',
                $entity->getOriginalName(),
                // only printable ascii
                preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $entity->getOriginalName())
            ),
            'Content-Length' => $this->filesystem->getSize($entity->getPath()),
            'Content-Type' => 'application/octet-stream',
        ];
        $stream = $this->filesystem->readStream($entity->getPath());
        if (!\is_resource($stream)) {
            throw new FileNotFoundException($fileId);
        }

        return new StreamedResponse(function () use ($stream): void {
            fpassthru($stream);
        }, Response::HTTP_OK, $headers);
    }

    private function findFile(Context $context, string $fileId): ImportExportFileEntity
    {
        $entity = $this->fileRepository->search(new Criteria([$fileId]), $context)->get($fileId);
        if ($entity === null) {
            throw new FileNotFoundException($fileId);
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
