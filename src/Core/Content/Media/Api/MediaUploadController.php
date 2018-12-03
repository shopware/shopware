<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\MissingFileExtensionException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MediaUploadController extends AbstractController
{
    /**
     * @var FileFetcher
     */
    private $fileFetcher;

    /**
     * @var FileSaver
     */
    private $fileSaver;

    public function __construct(FileFetcher $fileFetcher, FileSaver $fileSaver)
    {
        $this->fileFetcher = $fileFetcher;
        $this->fileSaver = $fileSaver;
    }

    /**
     * @Route("/api/v{version}/_action/media/{mediaId}/upload", name="api.action.media.upload", methods={"POST"})
     *
     * @return Response
     */
    public function upload(Request $request, string $mediaId, Context $context, ResponseFactoryInterface $responseFactory): Response
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');

        $destination = $request->query->get('fileName', $mediaId);

        try {
            $uploadedFile = $this->fetchFile($request, $tempFile);
            $this->fileSaver->persistFileToMedia(
                $uploadedFile,
                $destination,
                $mediaId,
                $context
            );
        } finally {
            unlink($tempFile);
        }

        return $responseFactory->createRedirectResponse(MediaDefinition::class, $mediaId, $request, $context);
    }

    /**
     * @Route("/api/v{version}/_action/media/{mediaId}/rename", name="api.action.media.rename", methods={"POST"})
     *
     * @return Response
     */
    public function renameMediaFile(Request $request, string $mediaId, Context $context, ResponseFactoryInterface $responseFactory): Response
    {
        $destination = $request->request->get('fileName');
        if ($destination === null) {
            throw new EmptyMediaFilenameException();
        }

        $this->fileSaver->renameMedia($mediaId, $destination, $context);

        return $responseFactory->createRedirectResponse(MediaDefinition::class, $mediaId, $request, $context);
    }

    /**
     * @throws MissingFileExtensionException
     * @throws UploadException
     */
    private function fetchFile(Request $request, string $tempFile): MediaFile
    {
        $contentType = $request->headers->get('content_type');
        if ($contentType === 'application/json') {
            return $this->fileFetcher->fetchFileFromURL($request, $tempFile);
        }

        return $this->fileFetcher->fetchRequestData($request, $tempFile);
    }
}
