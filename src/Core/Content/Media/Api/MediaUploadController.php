<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\MissingFileExtensionException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileNameProvider;
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

    /**
     * @var FileNameProvider
     */
    private $fileNameProvider;
    /**
     * @var MediaDefinition
     */
    private $mediaDefinition;

    public function __construct(
        FileFetcher $fileFetcher,
        FileSaver $fileSaver,
        FileNameProvider $fileNameProvider,
        MediaDefinition $mediaDefinition
    ) {
        $this->fileFetcher = $fileFetcher;
        $this->fileSaver = $fileSaver;
        $this->fileNameProvider = $fileNameProvider;
        $this->mediaDefinition = $mediaDefinition;
    }

    /**
     * @Route("/api/v{version}/_action/media/{mediaId}/upload", name="api.action.media.upload", methods={"POST"})
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

        return $responseFactory->createRedirectResponse($this->mediaDefinition, $mediaId, $request, $context);
    }

    /**
     * @Route("/api/v{version}/_action/media/{mediaId}/rename", name="api.action.media.rename", methods={"POST"})
     */
    public function renameMediaFile(Request $request, string $mediaId, Context $context, ResponseFactoryInterface $responseFactory): Response
    {
        $destination = $request->request->get('fileName');
        if ($destination === null) {
            throw new EmptyMediaFilenameException();
        }

        $this->fileSaver->renameMedia($mediaId, $destination, $context);

        return $responseFactory->createRedirectResponse($this->mediaDefinition, $mediaId, $request, $context);
    }

    /**
     * @Route("/api/v{version}/_action/media/provide-name", name="api.action.media.provide-name", methods={"GET"})
     */
    public function provideName(Request $request, Context $context): Response
    {
        $fileName = $request->query->get('fileName');
        $fileExtension = $request->query->get('extension');
        $mediaId = $request->query->get('mediaId');

        if ($fileName === null) {
            throw new EmptyMediaFilenameException();
        }
        if ($fileExtension === null) {
            throw new MissingFileExtensionException();
        }

        $name = $this->fileNameProvider->provide($fileName, $fileExtension, $mediaId, $context);

        return new Response(json_encode(['fileName' => $name]));
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
