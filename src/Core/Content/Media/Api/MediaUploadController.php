<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('buyers-experience')]
class MediaUploadController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly FileSaver $fileSaver,
        private readonly FileNameProvider $fileNameProvider,
        private readonly MediaDefinition $mediaDefinition,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route(path: '/api/_action/media/{mediaId}/upload', name: 'api.action.media.upload', methods: ['POST'])]
    public function upload(Request $request, string $mediaId, Context $context, ResponseFactoryInterface $responseFactory): Response
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');

        if (!$tempFile) {
            throw MediaException::cannotCreateTempFile();
        }

        $fileName = $request->query->getString('fileName', $mediaId);
        $destination = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $fileName);

        if (!\is_string($destination)) {
            throw MediaException::illegalFileName($fileName, 'Filename must be a string');
        }

        try {
            $uploadedFile = $this->mediaService->fetchFile($request, $tempFile);
            $this->fileSaver->persistFileToMedia(
                $uploadedFile,
                $destination,
                $mediaId,
                $context
            );

            $this->eventDispatcher->dispatch(new MediaUploadedEvent($mediaId, $context));
        } finally {
            unlink($tempFile);
        }

        return $responseFactory->createRedirectResponse($this->mediaDefinition, $mediaId, $request, $context);
    }

    #[Route(path: '/api/_action/media/{mediaId}/rename', name: 'api.action.media.rename', methods: ['POST'])]
    public function renameMediaFile(Request $request, string $mediaId, Context $context, ResponseFactoryInterface $responseFactory): Response
    {
        $fileName = $request->request->getString('fileName');
        $destination = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $fileName);

        if ($destination === '') {
            throw MediaException::emptyMediaFilename();
        }

        if (!\is_string($destination)) {
            throw MediaException::illegalFileName($fileName, 'Filename must be a string');
        }

        $this->fileSaver->renameMedia($mediaId, $destination, $context);

        return $responseFactory->createRedirectResponse($this->mediaDefinition, $mediaId, $request, $context);
    }

    #[Route(path: '/api/_action/media/provide-name', name: 'api.action.media.provide-name', methods: ['GET'])]
    public function provideName(Request $request, Context $context): JsonResponse
    {
        $fileName = $request->query->getString('fileName');
        $preferredFileName = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $fileName);

        if (!\is_string($preferredFileName)) {
            throw MediaException::illegalFileName($fileName, 'Filename must be a string');
        }

        $fileExtension = $request->query->getString('extension');
        $mediaId = $request->query->has('mediaId') ? $request->query->getString('mediaId') : null;

        if ($preferredFileName === '') {
            throw MediaException::emptyMediaFilename();
        }
        if ($fileExtension === '') {
            throw MediaException::missingFileExtension();
        }

        $name = $this->fileNameProvider->provide($preferredFileName, $fileExtension, $mediaId, $context);

        return new JsonResponse(['fileName' => $name]);
    }
}
