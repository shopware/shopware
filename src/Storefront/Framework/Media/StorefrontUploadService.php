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

class StorefrontUploadService
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
     * @var array
     */
    private $typeChecks;

    public function __construct(MediaService $mediaService, FileSaver $fileSaver)
    {
        $this->mediaService = $mediaService;
        $this->fileSaver = $fileSaver;
        $this->typeChecks = ['documents' => [$this, 'isDocument'], 'images' => [$this, 'isImage']];
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
        $this->checkTypeSafe($file, $type);

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

    public function addTypeCheck(string $typeName, callable $validator): bool
    {
        if (!isset($this->typeChecks[$typeName])) {
            $this->typeChecks[$typeName] = $validator;

            return true;
        }

        return false;
    }

    private function checkTypeSafe(UploadedFile $file, string $type): void
    {
        if (array_key_exists($type, $this->typeChecks)) {
            if (!$this->typeChecks[$type]($file)) {
                throw new FileTypeNotAllowedException($file->getMimeType(), $type);
            }
        } else {
            foreach ($this->typeChecks as $typeCheck) {
                if ($typeCheck($file)) {
                    return;
                }
            }

            throw new FileTypeNotAllowedException($file->getMimeType(), 'files');
        }
    }

    private function isDocument(UploadedFile $file): bool
    {
        if (!$this->checkMimeType($file, ['pdf' => ['application/pdf', 'application/x-pdf']])) {
            return false;
        }

        return true;
    }

    private function isImage(UploadedFile $file): bool
    {
        $allowedMimeTypes = [
            'jpe|jpg|jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ];

        if (!$this->checkMimeType($file, $allowedMimeTypes)) {
            return false;
        }

        if (getimagesize($file->getFilename())['mime'] !== $file->getMimeType()) {
            return false;
        }

        try {
            $image = new \Imagick($file->getFilename());
            $imageProfiles = $image->getImageProfiles('icc');
            $image->stripImage();
            $image->setImageProfile('icc', $imageProfiles['icc']);
            $image->writeImages($file->getFilename(), true);
        } catch (\ImagickException $e) {
            return false;
        }

        return true;
    }

    private function checkValidFile(UploadedFile $file): void
    {
        if ($file->isValid()) {
            throw new UploadException();
        }

        if (preg_match('/.+\.ph(p([3457s]|-s)?|t|tml)/', $file->getFilename())) {
            throw new IllegalFileNameException($file->getFilename(), 'contains PHP related file extension');
        }
    }

    private function checkMimeType(UploadedFile $file, array $allowedMimeTypes): bool
    {
        foreach ($allowedMimeTypes as $fileEndings => $mime) {
            if (in_array($file->getExtension(), explode('|', $fileEndings), true)) {
                $mimeTypes = $allowedMimeTypes[$fileEndings];
                if (
                    (is_string($mimeTypes) && $file->getMimeType() === $mimeTypes)
                    || (is_array($mimeTypes) && in_array($file->getMimeType(), $mimeTypes, true))
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
