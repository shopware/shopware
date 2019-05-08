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

class DownloadService
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var EntityRepositoryInterface
     */
    private $fileRepository;

    public function __construct(FilesystemInterface $filesystem, EntityRepositoryInterface $fileRepository)
    {
        $this->filesystem = $filesystem;
        $this->fileRepository = $fileRepository;
    }

    public function regenerateToken(Context $context, string $fileId): void
    {
        $this->fileRepository->update(
            [['id' => $fileId, 'accessToken' => ImportExportFileEntity::generateAccessToken()]],
            $context
        );
    }

    public function createFileResponse(Context $context, string $fileId, string $accessToken): Response
    {
        $entity = $this->findFile($context, $fileId);
        if ($entity->getAccessToken() !== $accessToken) {
            throw new InvalidFileAccessTokenException();
        }

        $headers = [
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $entity->getOriginalName()),
            'Content-Length' => $this->filesystem->getSize($entity->getPath()),
            'Content-Type' => 'application/octet-stream',
        ];
        $content = $this->filesystem->read($entity->getPath());

        return new Response($content, Response::HTTP_OK, $headers);
    }

    private function findFile(Context $context, string $fileId): ImportExportFileEntity
    {
        $entity = $this->fileRepository->search(new Criteria([$fileId]), $context)->get($fileId);
        if ($entity === null) {
            throw new FileNotFoundException($fileId);
        }

        return $entity;
    }
}
