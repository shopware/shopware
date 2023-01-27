<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileLoader;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

#[Package('content')]
class MediaService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $mediaFolderRepository,
        private readonly FileLoader $fileLoader,
        private readonly FileSaver $fileSaver,
        private readonly FileFetcher $fileFetcher
    ) {
    }

    public function createMediaInFolder(string $folder, Context $context, bool $private = true): string
    {
        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                    'private' => $private,
                    'mediaFolderId' => $this->getMediaDefaultFolderId($folder, $context),
                ],
            ],
            $context
        );

        return $mediaId;
    }

    public function saveMediaFile(
        MediaFile $mediaFile,
        string $filename,
        Context $context,
        ?string $folder = null,
        ?string $mediaId = null,
        bool $private = true
    ): string {
        if (!$mediaId) {
            $mediaId = $this->createMediaInFolder($folder ?? '', $context, $private);
        }

        $this->fileSaver->persistFileToMedia($mediaFile, $filename, $mediaId, $context);

        return $mediaId;
    }

    public function saveFile(
        string $blob,
        string $extension,
        string $contentType,
        string $filename,
        Context $context,
        ?string $folder = null,
        ?string $mediaId = null,
        bool $private = true
    ): string {
        $mediaFile = $this->fileFetcher->fetchBlob($blob, $extension, $contentType);

        if (!$mediaId) {
            $mediaId = $this->createMediaInFolder($folder ?? '', $context, $private);
        }

        $this->fileSaver->persistFileToMedia($mediaFile, $filename, $mediaId, $context);

        return $mediaId;
    }

    public function loadFile(string $mediaId, Context $context): string
    {
        return $this->fileLoader->loadMediaFile($mediaId, $context);
    }

    public function loadFileStream(string $mediaId, Context $context): StreamInterface
    {
        return $this->fileLoader->loadMediaFileStream($mediaId, $context);
    }

    public function fetchFile(Request $request, ?string $tempFile = null): MediaFile
    {
        if ($tempFile === null) {
            $tempFile = tempnam(sys_get_temp_dir(), '');
        }

        $contentType = $request->headers->get('content_type', '');

        if (str_starts_with($contentType, 'application/json')) {
            return $this->fileFetcher->fetchFileFromURL($request, $tempFile ?: '');
        }

        return $this->fileFetcher->fetchRequestData($request, $tempFile ?: '');
    }

    /**
     * @return array{content: string, fileName: non-falsy-string, mimeType: string|null}
     */
    public function getAttachment(MediaEntity $media, Context $context): array
    {
        $fileBlob = '';
        $mediaService = $this;
        $context->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($mediaService, $media, &$fileBlob): void {
            $fileBlob = $mediaService->loadFile($media->getId(), $context);
        });

        return [
            'content' => $fileBlob,
            'fileName' => $media->getFilename() . '.' . $media->getFileExtension(),
            'mimeType' => $media->getMimeType(),
        ];
    }

    private function getMediaDefaultFolderId(string $folder, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', $folder));
        $criteria->addAssociation('defaultFolder');
        $criteria->setLimit(1);
        $defaultFolder = $this->mediaFolderRepository->search($criteria, $context);
        $defaultFolderId = null;
        if ($defaultFolder->count() === 1) {
            $defaultFolderId = $defaultFolder->first()->getId();
        }

        return $defaultFolderId;
    }
}
