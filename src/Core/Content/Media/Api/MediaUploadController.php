<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use OpenApi\Annotations as OA;
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
     * @OA\Post(
     *     path="/_action/media/{mediaId}/upload",
     *     summary="Upload a file to a media entity",
     *     description="Adds a new file to a media entity. If the entity has an existing file, it will be replaced.

The are two methods of providing a file to this route:
 * Use a typical file upload and provide the file in the request
 * Fetch the file from an url. This only works if the `shopware.media.enable_url_upload_feature` variable is set to true in the shop environment.
To use file upload via url, the content type has to be `application/json` and the parameter `url` has to be provided.",
     *     operationId="upload",
     *     tags={"Admin API", "Asset Management"},
     *     @OA\RequestBody(
     *          @OA\MediaType(
     *              mediaType="application/octet-stream",
     *              @OA\Schema(
     *                  type="string",
     *                  format="binary"
     *              )
     *          ),
     *          @OA\JsonContent(
     *              required={
     *                  "url"
     *              },
     *              @OA\Property(
     *                  property="url",
     *                  description="The url of the media file that will be downloaded.",
     *                  type="string"
     *              )
     *          )
     *     ),
     *     @OA\Parameter(
     *         name="mediaId",
     *         description="Identifier of the media entity.",
     *         @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="fileName",
     *         description="Name of the uploaded file. If not provided the media identifier will be used as name",
     *         @OA\Schema(type="string"),
     *         in="query",
     *     ),
     *     @OA\Parameter(
     *         name="extension",
     *         description="Extension of the uploaded file. For example `png`",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         in="query",
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Media file uploaded successful",
     *         @OA\Header(
     *             header="Location",
     *             description="Contains the url to the uploaded media for a redirect.",
     *             @OA\Schema(type="string")
     *         )
     *     )
     * )
     * @Route("/api/_action/media/{mediaId}/upload", name="api.action.media.upload", methods={"POST"})
     */
    public function upload(Request $request, string $mediaId, Context $context, ResponseFactoryInterface $responseFactory): Response
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');

        $destination = $request->query->get('fileName', $mediaId);

        try {
            $uploadedFile = $this->mediaService->fetchFile($request, $tempFile);
            $this->fileSaver->persistFileToMedia(
                $uploadedFile,
                (string) $destination,
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
     * @Route("/api/_action/media/{mediaId}/rename", name="api.action.media.rename", methods={"POST"})
     */
    public function renameMediaFile(Request $request, string $mediaId, Context $context, ResponseFactoryInterface $responseFactory): Response
    {
        $destination = (string) $request->request->get('fileName');
        if ($destination === '') {
            throw new EmptyMediaFilenameException();
        }

        $this->fileSaver->renameMedia($mediaId, $destination, $context);

        return $responseFactory->createRedirectResponse($this->mediaDefinition, $mediaId, $request, $context);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/media/provide-name", name="api.action.media.provide-name", methods={"GET"})
     */
    public function provideName(Request $request, Context $context): JsonResponse
    {
        $fileName = (string) $request->query->get('fileName');
        $fileExtension = (string) $request->query->get('extension');
        $mediaId = $request->query->has('mediaId') ? (string) $request->query->get('mediaId') : null;

        if ($fileName === '') {
            throw new EmptyMediaFilenameException();
        }
        if ($fileExtension === '') {
            throw new MissingFileExtensionException();
        }

        $name = $this->fileNameProvider->provide($fileName, $fileExtension, $mediaId, $context);

        return new JsonResponse(['fileName' => $name]);
    }
}
