<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\MediaUploadParameters;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('discovery')]
readonly class MediaUploadV2Controller
{
    public function __construct(private MediaUploadService $mediaUploadService)
    {
    }

    #[Route(path: '/api/_action/media/upload', name: 'api.action.media.upload_v2', methods: ['POST'])]
    public function upload(Request $request, Context $context): Response
    {
        return new JsonResponse(['id' => $this->mediaUploadService->uploadFromRequest($request, $context, $this->buildMediaUploadParamsFromRequest($request))]);
    }

    #[Route(path: '/api/_action/media/upload_by_url', name: 'api.action.media.upload_v2_url', methods: ['POST'])]
    public function uploadUrl(Request $request, Context $context): Response
    {
        $url = $request->get('url');

        if (!\is_string($url)) {
            throw MediaException::invalidUrl($url ?? '');
        }

        return new JsonResponse(['id' => $this->mediaUploadService->uploadFromURL($url, $context, $this->buildMediaUploadParamsFromRequest($request))]);
    }

    #[Route(path: '/api/_action/media/external-link', name: 'api.action.media.external-link', methods: ['POST'])]
    public function externalLink(Request $request, Context $context): Response
    {
        $url = $request->get('url');

        if (!\is_string($url)) {
            throw MediaException::invalidUrl($url ?? '');
        }

        return new JsonResponse([
            'id' => $this->mediaUploadService->linkURL($url, $context, $this->buildMediaUploadParamsFromRequest($request)),
        ]);
    }

    private function buildMediaUploadParamsFromRequest(Request $request): MediaUploadParameters
    {
        $params = new MediaUploadParameters();

        $id = $request->get('id');
        $fileName = $request->get('fileName');
        $private = $request->get('private');
        $mediaFolderId = $request->get('mediaFolderId');
        $mimeType = $request->get('mimeType');
        $deduplicate = $request->get('deduplicate');

        if (\is_string($id)) {
            $params->id = $id;
        }

        if (\is_string($fileName)) {
            $params->fileName = $fileName;
        }

        if (\is_string($private) || \is_bool($private)) {
            $convert = filter_var($private, \FILTER_VALIDATE_BOOLEAN);

            if (\is_bool($convert)) {
                $params->private = $convert;
            }
        }

        if (\is_string($mediaFolderId)) {
            $params->mediaFolderId = $mediaFolderId;
        }

        if (\is_string($mimeType)) {
            $params->mimeType = $mimeType;
        }

        if (\is_string($deduplicate) || \is_bool($deduplicate)) {
            $convert = filter_var($deduplicate, \FILTER_VALIDATE_BOOLEAN);

            if (\is_bool($convert)) {
                $params->deduplicate = $convert;
            }
        }

        return $params;
    }
}
