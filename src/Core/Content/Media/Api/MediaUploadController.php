<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\Exception\MissingFileExtensionException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaDefinition;
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
     * @var FileSaver
     */
    private $fileSaver;

    public function __construct(ResponseFactory $responseFactory, FileFetcher $fileFetcher, FileSaver $fileSaver)
    {
        $this->responseFactory = $responseFactory;
        $this->fileFetcher = $fileFetcher;
        $this->fileSaver = $fileSaver;
    }

    /**
     * @Route("/api/v{version}/media/{mediaId}/actions/upload", name="api.media.actions.upload", methods={"POST"})
     *
     * @return Response
     */
    public function upload(Request $request, string $mediaId, Context $context): Response
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');

        try {
            $mediaFile = $this->fetchFile($request, $tempFile);
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                $mediaId,
                $context
            );
        } finally {
            unlink($tempFile);
        }

        return $this->responseFactory->createRedirectResponse(MediaDefinition::class, $mediaId, $request, $context);
    }

    /**
     * @throws MissingFileExtensionException
     * @throws UploadException
     */
    private function fetchFile(Request $request, string $tempFile): MediaFile
    {
        $contentType = $request->headers->get('content_type');
        $extension = $request->get('extension');
        if ($extension === null) {
            throw new MissingFileExtensionException();
        }

        $contentLength = (int) $request->headers->get('content-length');
        $mediaFile = new MediaFile($tempFile, $contentType, $extension, $contentLength);

        if ($mediaFile->getMimeType() === 'application/json') {
            $mediaFile = $this->fileFetcher->fetchFileFromURL($mediaFile, $request->request->get('url'));
        } else {
            $this->fileFetcher->fetchRequestData($request, $mediaFile);
        }

        return $mediaFile;
    }
}
