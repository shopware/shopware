<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

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

    public static function fromRequest(Request $request): self
    {
        $params = new self();

        $id = $request->get('id');
        $fileName = $request->get('fileName');
        $private = $request->get('private');
        $mediaFolderId = $request->get('mediaFolderId');
        $mimeType = $request->get('mimeType');
        $deduplicate = $request->get('deduplicate');

        if (\is_string($id)) {
            $params->id = $id;
        }

        if (\is_string($fileName)) {
            $params->fileName = $fileName;
        }

        if (\is_string($private) || \is_bool($private)) {
            $convert = filter_var($private, \FILTER_VALIDATE_BOOLEAN);

            if (\is_bool($convert)) {
                $params->private = $convert;
            }
        }

        if (\is_string($mediaFolderId)) {
            $params->mediaFolderId = $mediaFolderId;
        }

        if (\is_string($mimeType)) {
            $params->mimeType = $mimeType;
        }

        if (\is_string($deduplicate) || \is_bool($deduplicate)) {
            $convert = filter_var($deduplicate, \FILTER_VALIDATE_BOOLEAN);

            if (\is_bool($convert)) {
                $params->deduplicate = $convert;
            }
        }

        return $params;
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
