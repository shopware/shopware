<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\File\PrivateFileDownloadResponseGenerator;
use Shopware\Core\Content\Media\Util\PathHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class DownloadService
{
    /**
     * @internal
     *
     * @param EntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly EntityRepository $fileRepository,
        private readonly string $localDownloadStrategy,
        private readonly RateLimiter $rateLimiter,
        private readonly string $localPathPrefix,
        private readonly ClockInterface $clock,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly PrivateFileDownloadResponseGenerator $privateFileDownloadResponseGenerator
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

    public function createFileResponse(Context $context, string $fileId, string $accessToken, string $clientIp): Response
    {
        $cacheKey = $fileId . '-' . $clientIp;

        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::IMPORT_EXPORT_FILE_DOWNLOAD, $cacheKey);
        } catch (RateLimitExceededException $exception) {
            throw ImportExportException::fileDownloadThrottledException($exception->getWaitTime());
        }

        $entity = $this->findFile($context, $fileId);

        $fileAccessToken = (string) $entity->getAccessToken();

        if ($fileAccessToken === '' || $entity->getAccessToken() !== $accessToken || !$this->isModifiedRecently($entity)) {
            throw ImportExportException::invalidFileAccessToken();
        }

        $this->fileRepository->update(
            [['id' => $fileId, 'accessToken' => null]],
            $context
        );

        $this->rateLimiter->reset(RateLimiter::IMPORT_EXPORT_FILE_DOWNLOAD, $cacheKey);

        return $this->privateFileDownloadResponseGenerator->createResponse(
            streamProvider: function () use ($entity, $fileId): StreamInterface {
                $stream = $this->filesystem->readStream($entity->getPath());
                if (!\is_resource($stream)) {
                    throw ImportExportException::fileNotFound($fileId);
                }

                return $this->streamFactory->createStreamFromResource($stream);
            },
            headers: $this->getStreamHeaders($entity),
            downloadStrategy: $this->localDownloadStrategy,
            path: $entity->getPath(),
            pathPrefix: $this->localPathPrefix,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getStreamHeaders(ImportExportFileEntity $entity): array
    {
        $downloadHeaders = $this->getDownloadHeaders($entity);

        return [
            'Content-Disposition' => $downloadHeaders['Content-Disposition'],
            'Content-Length' => $this->filesystem->fileSize($entity->getPath()),
            'Content-Type' => $downloadHeaders['Content-Type'],
        ];
    }

    /**
     * @return array{'Content-Disposition': string, 'Content-Type': string}
     */
    private function getDownloadHeaders(ImportExportFileEntity $entity): array
    {
        $originalName = (string) preg_replace('/[\/\\\]/', '', $entity->getOriginalName());

        try {
            $filenameFallback = PathHelper::stripNonAsciiAndControlChars($originalName);
        } catch (IllegalFileNameException) {
            $filenameFallback = '';
        }

        return [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                'attachment',
                $originalName,
                // only printable ascii
                $filenameFallback
            ),
            'Content-Type' => $this->resolveContentType($originalName),
        ];
    }

    private function findFile(Context $context, string $fileId): ImportExportFileEntity
    {
        $entity = $this->fileRepository->search(new Criteria([$fileId]), $context)->getEntities()->get($fileId);

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

        $diff = $this->clock->now()->getTimestamp() - $entity->getUpdatedAt()->getTimestamp();

        return $diff < 300;
    }

    private function resolveContentType(string $originalName): string
    {
        return match (strtolower((string) pathinfo($originalName, \PATHINFO_EXTENSION))) {
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }
}
