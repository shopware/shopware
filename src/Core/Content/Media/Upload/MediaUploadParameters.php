<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('discovery')]
class MediaUploadParameters
{
    public function __construct(
        public ?string $id = null,
        public ?string $mediaFolderId = null,
        public ?bool $private = null,
        public ?string $fileName = null,
        public ?string $mimeType = null,
        public ?bool $deduplicate = null
    ) {
    }

    /**
     * @phpstan-assert !null $this->fileName
     */
    public function fillDefaultFileName(string $fileName): void
    {
        if ($this->fileName) {
            return;
        }

        $this->fileName = $fileName;
    }

    public function getFileNameWithoutExtension(): string
    {
        if ($this->fileName === null) {
            throw MediaException::emptyMediaFilename();
        }

        $extension = pathinfo($this->fileName, \PATHINFO_EXTENSION);

        return mb_substr($this->fileName, 0, mb_strlen($this->fileName) - mb_strlen($extension) - 1);
    }

    public function getFileNameExtension(): string
    {
        if ($this->fileName === null) {
            throw MediaException::emptyMediaFilename();
        }

        return pathinfo($this->fileName, \PATHINFO_EXTENSION);
    }
}
