<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;
use Shopware\Core\Content\Media\Core\Params\UrlParams;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Package('buyers-experience')]
class DownloadResponseGenerator
{
    final public const X_SENDFILE_DOWNLOAD_STRATEGRY = 'x-sendfile';
    final public const X_ACCEL_DOWNLOAD_STRATEGRY = 'x-accel';

    /**
     * @internal
     */
    public function __construct(
        private readonly FilesystemOperator $filesystemPublic,
        private readonly FilesystemOperator $filesystemPrivate,
        private readonly MediaService $mediaService,
        private readonly string $localPrivateDownloadStrategy,
        private readonly AbstractMediaUrlGenerator $mediaUrlGenerator
    ) {
    }

    public function getResponse(
        MediaEntity $media,
        SalesChannelContext $context,
        string $expiration = '+120 minutes'
    ): Response {
        $fileSystem = $this->getFileSystem($media);

        $path = $media->getPath();

        try {
            $url = $fileSystem->temporaryUrl($path, (new \DateTime())->modify($expiration));

            return new RedirectResponse($url);
        } catch (UnableToGenerateTemporaryUrl) {
        }

        return $this->getDefaultResponse($media, $context, $fileSystem);
    }

    private function getDefaultResponse(MediaEntity $media, SalesChannelContext $context, FilesystemOperator $fileSystem): Response
    {
        if (!$media->isPrivate()) {
            $url = $this->mediaUrlGenerator->generate([UrlParams::fromMedia($media)]);

            return new RedirectResponse((string) array_shift($url));
        }

        switch ($this->localPrivateDownloadStrategy) {
            case self::X_SENDFILE_DOWNLOAD_STRATEGRY:
                $location = $media->getPath();

                $stream = $fileSystem->readStream($location);
                $location = \is_resource($stream) ? stream_get_meta_data($stream)['uri'] : $location;

                $response = new Response(null, 200, $this->getStreamHeaders($media));
                $response->headers->set('X-Sendfile', $location);

                return $response;
            case self::X_ACCEL_DOWNLOAD_STRATEGRY:
                $location = $media->getPath();

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
            fn (Context $context): StreamInterface => $this->mediaService->loadFileStream($media->getId(), $context)
        );

        if (!$stream instanceof StreamInterface) {
            throw MediaException::fileNotFound($media->getFilename() . '.' . $media->getFileExtension());
        }

        $stream = $stream->detach();

        if (!\is_resource($stream)) {
            if (!Feature::isActive('v6.6.0.0')) {
                throw new FileNotFoundException($media->getFilename() . '.' . $media->getFileExtension());
            }

            throw MediaException::fileNotFound($media->getFilename() . '.' . $media->getFileExtension());
        }

        return new StreamedResponse(function () use ($stream): void {
            fpassthru($stream);
        }, Response::HTTP_OK, $this->getStreamHeaders($media));
    }

    private function getFileSystem(MediaEntity $media): FilesystemOperator
    {
        if ($media->isPrivate()) {
            $filesystem = $this->filesystemPrivate;
        } else {
            $filesystem = $this->filesystemPublic;
        }

        if (!$filesystem instanceof Filesystem) {
            throw MediaException::fileIsNotInstanceOfFileSystem();
        }

        return $filesystem;
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
