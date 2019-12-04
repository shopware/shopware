<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Media;

use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\Exception\MediaNotFoundException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Framework\Media\Exception\FileTypeNotAllowedException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class StorefrontMediaUploader
{
    /**
     * @var FileSaver
     */
    private $fileSaver;

    /**
     * @var MediaService
     */
    private $mediaService;

    /**
     * @var StorefrontMediaValidatorRegistry
     */
    private $validator;

    public function __construct(MediaService $mediaService, FileSaver $fileSaver, StorefrontMediaValidatorRegistry $validator)
    {
        $this->mediaService = $mediaService;
        $this->fileSaver = $fileSaver;
        $this->validator = $validator;
    }

    /**
     * @throws FileTypeNotAllowedException
     * @throws IllegalFileNameException
     * @throws UploadException
     * @throws DuplicatedMediaFileNameException
     * @throws EmptyMediaFilenameException
     */
    public function upload(UploadedFile $file, string $folder, string $type, Context $context): string
    {
        $this->checkValidFile($file);

        $this->validator->validate($file, $type);

        $mediaFile = new MediaFile($file->getPathname(), $file->getMimeType(), $file->getExtension(), $file->getSize());

        $mediaId = $this->mediaService->createMediaInFolder($folder, $context, false);

        try {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                pathinfo($file->getFilename(), PATHINFO_FILENAME),
                $mediaId,
                $context
            );
        } catch (MediaNotFoundException $e) {
            throw new UploadException($e->getMessage());
        }

        return $mediaId;
    }

    private function checkValidFile(UploadedFile $file): void
    {
        if ($file->isValid()) {
            throw new UploadException($file->getErrorMessage());
        }

        if (preg_match('/.+\.ph(p([3457s]|-s)?|t|tml)/', $file->getFilename())) {
            throw new IllegalFileNameException($file->getFilename(), 'contains PHP related file extension');
        }
    }
}
