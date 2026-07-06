<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Media\Util\PathHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('discovery')]
class MediaDownloadController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly MediaService $mediaService
    ) {
    }

    #[Route(
        path: '/api/_action/media/{mediaId}/download',
        name: 'api.action.media.download',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['media:read']],
        methods: [Request::METHOD_GET]
    )]
    public function downloadMediaFile(string $mediaId, Context $context): Response
    {
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $context)->first();

        if (!$media instanceof MediaEntity) {
            throw MediaException::mediaNotFound($mediaId);
        }

        return $this->createStreamedResponse($media, $context);
    }

    private function createStreamedResponse(MediaEntity $media, Context $context): StreamedResponse
    {
        $stream = $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $context): StreamInterface => $this->mediaService->loadFileStream($media->getId(), $context)
        );

        if (!$stream instanceof StreamInterface) {
            throw MediaException::fileNotFound($media->getFileName() . '.' . $media->getFileExtension());
        }

        $stream = $stream->detach();

        if (!\is_resource($stream)) {
            throw MediaException::fileNotFound($media->getFileName() . '.' . $media->getFileExtension());
        }

        return new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
        }, Response::HTTP_OK, $this->getStreamHeaders($media));
    }

    /**
     * @return array<string, mixed>
     */
    private function getStreamHeaders(MediaEntity $media): array
    {
        $filename = $media->getFileName() . '.' . $media->getFileExtension();

        try {
            $filenameFallback = PathHelper::stripNonAsciiAndControlChars($filename);
        } catch (IllegalFileNameException) {
            $filenameFallback = '';
        }

        return [
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                // only printable ascii
                $filenameFallback
            ),
            'Content-Length' => $media->getFileSize() ?? 0,
            'Content-Type' => 'application/octet-stream',
        ];
    }
}
