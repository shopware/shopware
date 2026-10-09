<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class MediaDownloadController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly DownloadResponseGenerator $downloadResponseGenerator
    ) {
    }

    #[Route(
        path: '/api/_action/media/{mediaId}/download/prepare',
        name: 'api.action.media.download.prepare',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['media:read']],
        methods: [Request::METHOD_GET]
    )]
    public function prepareMediaDownload(string $mediaId, Context $context): JsonResponse
    {
        $media = $this->getMedia($mediaId, $context);
        $response = $this->downloadResponseGenerator->getResponseByContext($media, $context);

        if ($response instanceof RedirectResponse) {
            return new JsonResponse([
                'type' => 'external',
                'url' => $response->getTargetUrl(),
            ]);
        }

        return new JsonResponse(['type' => 'blob']);
    }

    #[Route(
        path: '/api/_action/media/{mediaId}/download',
        name: 'api.action.media.download',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['media:read']],
        methods: [Request::METHOD_GET]
    )]
    public function downloadMediaFile(string $mediaId, Context $context): Response
    {
        $media = $this->getMedia($mediaId, $context);

        return $this->downloadResponseGenerator->getResponseByContext($media, $context);
    }

    private function getMedia(string $mediaId, Context $context): MediaEntity
    {
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $context)->getEntities()->first();

        if (!$media instanceof MediaEntity) {
            throw MediaException::mediaNotFound($mediaId);
        }

        return $media;
    }
}
