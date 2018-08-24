<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\Exception\MissingFileExtensionException;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\Upload\FileFetcher;
use Shopware\Core\Content\Media\Upload\MediaUpdater;
use Shopware\Core\Framework\Api\Response\ResponseFactory;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MediaUploadController extends Controller
{
    /**
     * @var ResponseFactory
     */
    private $responseFactory;

    /**
     * @var FileFetcher
     */
    private $fileFetcher;

    /**
     * @var MediaUpdater
     */
    private $mediaUpdater;

    /**
     * @param ResponseFactory $responseFactory
     * @param FileFetcher     $fileFetcher
     * @param MediaUpdater    $mediaUpdater
     */
    public function __construct(ResponseFactory $responseFactory, FileFetcher $fileFetcher, MediaUpdater $mediaUpdater)
    {
        $this->responseFactory = $responseFactory;
        $this->fileFetcher = $fileFetcher;
        $this->mediaUpdater = $mediaUpdater;
    }

    /**
     * @Route("/api/v{version}/media/{mediaId}/actions/upload", name="api.media.actions.upload", methods={"POST"})
     *
     * @param Request $request
     * @param string  $mediaId
     * @param Context $context
     *
     * @return Response
     */
    public function upload(Request $request, string $mediaId, Context $context): Response
    {
        $contentType = $request->headers->get('content_type');
        $extension = $request->get('extension');
        if ($extension === null) {
            throw new MissingFileExtensionException();
        }

        $tempFile = tempnam(sys_get_temp_dir(), '');

        try {
            $contentLength = $this->fetchFile($request, $contentType, $tempFile);
            $contentType = mime_content_type($tempFile);
            $this->mediaUpdater->persistFileToMedia(
                $tempFile,
                $mediaId,
                $contentType,
                $extension,
                $contentLength,
                $context);
        } finally {
            unlink($tempFile);
        }

        return $this->responseFactory->createRedirectResponse(MediaDefinition::class, $mediaId, $request, $context);
    }

    /**
     * @param Request $request
     * @param string  $contentType
     * @param string  $tempFile
     *
     * @return int
     */
    private function fetchFile(Request $request, string $contentType, string $tempFile): int
    {
        if ($contentType === 'application/json') {
            $contentLength = $this->fileFetcher->fetchFileFromURL($tempFile, $request->request->get('url'));
        } else {
            $contentLength = (int) $request->headers->get('content-length');
            $this->fileFetcher->fetchRequestData($request, $tempFile, $contentType, $contentLength);
        }

        return $contentLength;
    }
}
