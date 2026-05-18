<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Media\Util\PathHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class DownloadService
{
    private const EXPIRATION_TIME = '+120 minutes';

    /**
     * @internal
     *
     * @param EntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly EntityRepository $fileRepository,
        private readonly LoggerInterface $logger,
        private readonly string $localDownloadStrategy,
        private readonly string $localPathPrefix,
        private readonly ClockInterface $clock
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

        try {
            $url = $this->filesystem->temporaryUrl(
                $entity->getPath(),
                $this->clock->now()->modify(self::EXPIRATION_TIME),
                $this->getTemporaryUrlConfig($entity)
            );

            return new RedirectResponse($url);
        } catch (UnableToGenerateTemporaryUrl $exception) {
            $this->logger->warning($exception->getMessage(), ['exception' => $exception]);
        } catch (\Exception $exception) {
            $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
        }

        return $this->createResponse($entity, $fileId);
    }

    private function createResponse(ImportExportFileEntity $entity, string $fileId): Response
    {
        switch ($this->localDownloadStrategy) {
            case DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY:
                $location = $entity->getPath();

                $stream = $this->filesystem->readStream($location);
                if (!\is_resource($stream)) {
                    throw ImportExportException::fileNotFound($fileId);
                }

                $location = stream_get_meta_data($stream)['uri'] ?? $location;

                $response = new Response(null, Response::HTTP_OK, $this->getStreamHeaders($entity));
                $response->headers->set(DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY, $location);

                return $response;
            case DownloadResponseGenerator::X_ACCEL_DOWNLOAD_STRATEGY:
                $location = $entity->getPath();

                if ($this->localPathPrefix !== '') {
                    $location = $this->localPathPrefix . '/' . ltrim($location, '/');
                }

                $response = new Response(null, Response::HTTP_OK, $this->getStreamHeaders($entity));
                $response->headers->set(DownloadResponseGenerator::X_ACCEL_REDIRECT, $location);

                return $response;
            default:
                return $this->createStreamedResponse($entity, $fileId);
        }
    }

    private function createStreamedResponse(ImportExportFileEntity $entity, string $fileId): StreamedResponse
    {
        $stream = $this->filesystem->readStream($entity->getPath());
        if (!\is_resource($stream)) {
            throw ImportExportException::fileNotFound($fileId);
        }

        return new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
        }, Response::HTTP_OK, $this->getStreamHeaders($entity));
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
     * S3 temporary URLs use GetObject response overrides to preserve the download
     * filename and content type after redirecting away from Shopware.
     *
     * @return array{get_object_options: array{ResponseContentDisposition: string, ResponseContentType: string}}
     */
    private function getTemporaryUrlConfig(ImportExportFileEntity $entity): array
    {
        $downloadHeaders = $this->getDownloadHeaders($entity);

        return [
            'get_object_options' => [
                'ResponseContentDisposition' => $downloadHeaders['Content-Disposition'],
                'ResponseContentType' => $downloadHeaders['Content-Type'],
            ],
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
