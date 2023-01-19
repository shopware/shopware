<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Superbalist\Flysystem\GoogleStorage\GoogleStorageAdapter;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Package('content')]
class DownloadResponseGenerator
{
    public const X_SENDFILE_DOWNLOAD_STRATEGRY = 'x-sendfile';
    public const X_ACCEL_DOWNLOAD_STRATEGRY = 'x-accel';

    private FilesystemInterface $filesystemPublic;

    private FilesystemInterface $filesystemPrivate;

    private UrlGeneratorInterface $urlGenerator;

    private MediaService $mediaService;

    private string $localPrivateDownloadStrategory;

    /**
     * @internal
     */
    public function __construct(
        FilesystemInterface $filesystemPublic,
        FilesystemInterface $filesystemPrivate,
        UrlGeneratorInterface $urlGenerator,
        MediaService $mediaService,
        string $localPrivateDownloadStrategory
    ) {
        $this->filesystemPublic = $filesystemPublic;
        $this->filesystemPrivate = $filesystemPrivate;
        $this->urlGenerator = $urlGenerator;
        $this->mediaService = $mediaService;
        $this->localPrivateDownloadStrategory = $localPrivateDownloadStrategory;
    }

    public function getResponse(
        MediaEntity $media,
        SalesChannelContext $context,
        string $expiration = '+120 minutes'
    ): Response {
        $adapter = $this->getFileSystemAdapter($media);

        if ($adapter instanceof AwsS3Adapter) {
            return $this->getAwsResponse($adapter, $media, $expiration);
        }

        if ($adapter instanceof GoogleStorageAdapter) {
            return $this->getGoogleStorageResponse($adapter, $media, $expiration);
        }

        return $this->getLocalResponse($adapter, $media, $context);
    }

    private function getLocalResponse(AdapterInterface $adapter, MediaEntity $media, SalesChannelContext $context): Response
    {
        if (!$media->isPrivate()) {
            return new RedirectResponse($this->urlGenerator->getAbsoluteMediaUrl($media));
        }

        switch ($this->localPrivateDownloadStrategory) {
            case self::X_SENDFILE_DOWNLOAD_STRATEGRY:
                $pathPrefix = $adapter instanceof AbstractAdapter ? $adapter->getPathPrefix() : '';
                $location = $pathPrefix . $this->urlGenerator->getRelativeMediaUrl($media);

                $response = new Response(null, 200, $this->getStreamHeaders($media));
                $response->headers->set('X-Sendfile', $location);

                return $response;
            case self::X_ACCEL_DOWNLOAD_STRATEGRY:
                $location = $this->urlGenerator->getRelativeMediaUrl($media);

                $response = new Response(null, 200, $this->getStreamHeaders($media));
                $response->headers->set('X-Accel-Redirect', $location);

                return $response;
            default:
                return $this->createStreamedResponse(
                    $media,
                    $context
                );
        }
    }

    private function createStreamedResponse(MediaEntity $media, SalesChannelContext $context): StreamedResponse
    {
        $stream = $context->getContext()->scope(
            Context::SYSTEM_SCOPE,
            function (Context $context) use ($media): StreamInterface {
                return $this->mediaService->loadFileStream($media->getId(), $context);
            }
        )->detach();

        if (!\is_resource($stream)) {
            throw new FileNotFoundException($media->getFilename() . '.' . $media->getFileExtension());
        }

        return new StreamedResponse(function () use ($stream): void {
            fpassthru($stream);
        }, Response::HTTP_OK, $this->getStreamHeaders($media));
    }

    private function getAwsResponse(AwsS3Adapter $adapter, MediaEntity $media, string $expiration): RedirectResponse
    {
        $path = $this->urlGenerator->getRelativeMediaUrl($media);
        $bucket = $adapter->getBucket();
        $client = $adapter->getClient();

        $command = $client->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key' => $adapter->applyPathPrefix($path),
        ]);

        $request = $client->createPresignedRequest($command, $expiration);

        return new RedirectResponse((string) $request->getUri());
    }

    private function getGoogleStorageResponse(GoogleStorageAdapter $adapter, MediaEntity $media, string $expiration): RedirectResponse
    {
        $path = $this->urlGenerator->getRelativeMediaUrl($media);

        return new RedirectResponse($adapter->getTemporaryUrl($path, (new \DateTime())->modify($expiration)));
    }

    private function getFileSystemAdapter(MediaEntity $media): AdapterInterface
    {
        if ($media->isPrivate()) {
            $filesystem = $this->filesystemPrivate;
        } else {
            $filesystem = $this->filesystemPublic;
        }

        if (!$filesystem instanceof Filesystem) {
            throw new \RuntimeException(sprintf('Filesystem is not an instance of %s', Filesystem::class));
        }

        return $filesystem->getAdapter();
    }

    /**
     * @return array<string, mixed>
     */
    private function getStreamHeaders(MediaEntity $media): array
    {
        $filename = $media->getFilename() . '.' . $media->getFileExtension();

        return [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                // only printable ascii
                preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $filename) ?? ''
            ),
            'Content-Length' => $media->getFileSize() ?? 0,
            'Content-Type' => 'application/octet-stream',
        ];
    }
}
