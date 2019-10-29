<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileLoader;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

class MediaService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaFolderRepository;

    /**
     * @var FileLoader
     */
    private $fileLoader;

    /**
     * @var FileSaver
     */
    private $fileSaver;

    /**
     * @var FileFetcher
     */
    private $fileFetcher;

    public function __construct(
        EntityRepositoryInterface $mediaRepository,
        EntityRepositoryInterface $mediaFolderRepository,
        FileLoader $fileLoader,
        FileSaver $fileSaver,
        FileFetcher $fileFetcher
    ) {
        $this->mediaRepository = $mediaRepository;
        $this->mediaFolderRepository = $mediaFolderRepository;
        $this->fileLoader = $fileLoader;
        $this->fileSaver = $fileSaver;
        $this->fileFetcher = $fileFetcher;
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
        ?string $mediaId = null
    ): string {
        if (!$mediaId) {
            $mediaId = $this->createMediaInFolder($folder, $context);
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
            $mediaId = $this->createMediaInFolder($folder, $context, $private);
        }

        $this->fileSaver->persistFileToMedia($mediaFile, $filename, $mediaId, $context);

        return $mediaId;
    }

    public function loadFile(string $mediaId, Context $context): string
    {
        return $this->fileLoader->loadMediaFile($mediaId, $context);
    }

    public function fetchFile(Request $request, ?string $tempFile = null): MediaFile
    {
        if ($tempFile === null) {
            $tempFile = tempnam(sys_get_temp_dir(), '');
        }

        $contentType = $request->headers->get('content_type');
        if ($contentType === 'application/json') {
            return $this->fileFetcher->fetchFileFromURL($request, $tempFile);
        }

        return $this->fileFetcher->fetchRequestData($request, $tempFile);
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
