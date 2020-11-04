<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\MissingFileExtensionException;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class MediaUploadController extends AbstractController
{
    /**
     * @var MediaService
     */
    private $mediaService;

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
        MediaService $mediaService,
        FileSaver $fileSaver,
        FileNameProvider $fileNameProvider,
        MediaDefinition $mediaDefinition
    ) {
        $this->mediaService = $mediaService;
        $this->fileSaver = $fileSaver;
        $this->fileNameProvider = $fileNameProvider;
        $this->mediaDefinition = $mediaDefinition;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/v{version}/_action/media/{mediaId}/upload", name="api.action.media.upload", methods={"POST"})
     */
    public function upload(Request $request, string $mediaId, Context $context, ResponseFactoryInterface $responseFactory): Response
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');

        $destination = $request->query->get('fileName', $mediaId);

        try {
            $uploadedFile = $this->mediaService->fetchFile($request, $tempFile);
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
     * @Since("6.0.0.0")
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
     * @Since("6.0.0.0")
     * @Route("/api/v{version}/_action/media/provide-name", name="api.action.media.provide-name", methods={"GET"})
     */
    public function provideName(Request $request, Context $context): JsonResponse
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

        return new JsonResponse(['fileName' => $name]);
    }
}
