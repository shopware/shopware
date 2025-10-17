<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Api;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Media\Upload\PresignedUploadUrlGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class MediaPresignedUploadController extends AbstractController
{
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly FilesystemOperator $publicFilesystem,
        private readonly PresignedUploadUrlGenerator $presignedUrlGenerator,
    ) {
    }

    #[Route(
        path: '/api/_action/media/presigned-upload/prepare',
        name: 'api.action.media.presigned_upload.prepare',
        methods: ['POST']
    )]
    public function prepare(Request $request, Context $context): JsonResponse
    {
        $data = $request->toArray();
        $fileName = $data['fileName'] ?? null;
        $extension = $data['extension'] ?? null;
        $mimeType = $data['mimeType'] ?? 'application/octet-stream';
        $mediaFolderId = $data['mediaFolderId'] ?? null;

        if (!$fileName || !$extension) {
            return new JsonResponse([
                'errors' => [
                    ['detail' => 'fileName and extension are required']
                ]
            ], 400);
        }

        $presignedData = $this->presignedUrlGenerator->generatePresignedUrl(
            $fileName,
            $extension,
            $mimeType,
            $mediaFolderId
        );

        if (!$presignedData) {
            return new JsonResponse([
                'errors' => [
                    ['detail' => 'S3 storage not configured. Presigned URLs require S3.']
                ]
            ], 400);
        }

        $mediaData = [
            'id' => $presignedData['mediaId'],
            'fileName' => pathinfo($fileName, \PATHINFO_FILENAME),
            'fileExtension' => $extension,
            'mimeType' => $mimeType,
            'uploadedAt' => new \DateTime(),
            'path' => $presignedData['s3Key'],
        ];

        if ($mediaFolderId) {
            $mediaData['mediaFolderId'] = $mediaFolderId;
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($mediaData): void {
            $this->mediaRepository->create([$mediaData], $context);
        });

        return new JsonResponse([
            'mediaId' => $presignedData['mediaId'],
            'uploadUrl' => $presignedData['url'],
            'path' => $presignedData['s3Key'],
            'expiresAt' => $presignedData['expiresAt'],
        ]);
    }

    #[Route(
        path: '/api/_action/media/presigned-upload/finalize',
        name: 'api.action.media.presigned_upload.finalize',
        methods: ['POST']
    )]
    public function finalize(Request $request, Context $context): JsonResponse
    {
        $data = $request->toArray();
        $mediaId = $data['mediaId'] ?? null;
        $s3Key = $data['path'] ?? null;

        if (!$mediaId || !$s3Key) {
            return new JsonResponse([
                'errors' => [
                    ['detail' => 'mediaId and path are required']
                ]
            ], 400);
        }

        // verify the file was uploaded to S3
        if (!$this->presignedUrlGenerator->verifyUpload($s3Key)) {
            return new JsonResponse([
                'errors' => [
                    ['detail' => "File not found in S3: {$s3Key}"]
                ]
            ], 404);
        }

        $fileMetadata = $this->presignedUrlGenerator->getFileMetadata($s3Key);

        if (!$fileMetadata) {
            return new JsonResponse([
                'errors' => [
                    ['detail' => "Could not retrieve file metadata from S3: {$s3Key}"]
                ]
            ], 404);
        }

        $mimeType = $this->publicFilesystem->mimeType($s3Key);

        $updateData = [
            'id' => $mediaId,
            'fileSize' => $fileMetadata['size'],
            'mimeType' => $mimeType,
            'path' => $s3Key,
            'uploadedAt' => new \DateTime(),
        ];

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($updateData): void {
            $this->mediaRepository->update([$updateData], $context);
        });

        return new JsonResponse([
            'success' => true,
            'mediaId' => $mediaId,
            'fileSize' => $fileMetadata['size'],
            'mimeType' => $mimeType,
            'path' => $s3Key,
        ]);
    }
}

