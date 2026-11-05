<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Rejects GLB files that reference external resources.
 *
 * glTF loaders resolve the `uri` of every buffer and image relative to the model URL and fetch it. A manipulated GLB could therefore make every visitor's
 * browser request third-party URLs, which allows tracking. A valid GLB is self-contained: its
 * resources live in the binary chunk or are embedded as `data:` URIs.
 *
 * @see https://registry.khronos.org/glTF/specs/2.0/glTF-2.0.html#glb-file-format-specification
 *
 * @internal
 */
#[Package('discovery')]
class GlbContentValidator extends AbstractFileContentValidator
{
    private const GLB = 'glb';
    private const MAGIC = 0x46546C67;
    private const SUPPORTED_VERSION = 2;
    private const JSON_CHUNK_TYPE = 0x4E4F534A;
    private const HEADER_LENGTH = 12;
    private const CHUNK_HEADER_LENGTH = 8;
    private const URI = 'uri';
    private const RESOURCE_COLLECTIONS = ['buffers', 'images'];
    private const DATA_URI_PREFIX = 'data:';
    private const INVALID_GLB_MESSAGE = 'The file is not a valid GLB (binary glTF 2.0) document.';
    private const EXTERNAL_REFERENCE_MESSAGE = 'GLB files with external references are not allowed';

    public function getDecorated(): AbstractFileContentValidator
    {
        throw new DecorationPatternException(self::class);
    }

    public function supports(MediaFile $mediaFile): bool
    {
        return mb_strtolower($mediaFile->getFileExtension()) === self::GLB;
    }

    public function validate(MediaFile $mediaFile): void
    {
        if ($this->supports($mediaFile) === false) {
            return;
        }

        $externalReferences = $this->findExternalReferences($this->readJsonChunk($mediaFile));

        if ($externalReferences !== []) {
            throw MediaException::invalidFile(\sprintf('%s: %s', self::EXTERNAL_REFERENCE_MESSAGE, implode(', ', $externalReferences)));
        }
    }

    /**
     * Three.js' GLTFLoader falls back to parsing plain glTF JSON when the binary magic is missing,
     * so anything that is not a well-formed GLB container is rejected instead of being skipped.
     *
     * @return array<mixed>
     */
    private function readJsonChunk(MediaFile $mediaFile): array
    {
        $fileName = $mediaFile->getFileName();
        $fileSize = filesize($fileName);

        $headers = file_get_contents($fileName, false, null, 0, self::HEADER_LENGTH + self::CHUNK_HEADER_LENGTH);
        if (!\is_int($fileSize) || !\is_string($headers) || \strlen($headers) !== self::HEADER_LENGTH + self::CHUNK_HEADER_LENGTH) {
            throw MediaException::invalidFile(self::INVALID_GLB_MESSAGE);
        }

        /** @var array{magic: int, version: int, length: int, chunkLength: int, chunkType: int} $header */
        $header = unpack('Vmagic/Vversion/Vlength/VchunkLength/VchunkType', $headers);

        $isValidHeader = $header['magic'] === self::MAGIC
            && $header['version'] === self::SUPPORTED_VERSION
            && $header['length'] <= $fileSize
            && $header['chunkType'] === self::JSON_CHUNK_TYPE
            && $header['chunkLength'] > 0
            && self::HEADER_LENGTH + self::CHUNK_HEADER_LENGTH + $header['chunkLength'] <= $header['length'];

        if (!$isValidHeader) {
            throw MediaException::invalidFile(self::INVALID_GLB_MESSAGE);
        }

        $json = (string) file_get_contents($fileName, false, null, self::HEADER_LENGTH + self::CHUNK_HEADER_LENGTH, $header['chunkLength']);

        try {
            $document = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw MediaException::invalidFile(self::INVALID_GLB_MESSAGE);
        }

        if (!\is_array($document)) {
            throw MediaException::invalidFile(self::INVALID_GLB_MESSAGE);
        }

        return $document;
    }

    /**
     * Only buffers and images are fetched by three.js' GLTFLoader, texture extensions reference images by index.
     *
     * @param array<mixed> $document
     *
     * @return list<string>
     */
    private function findExternalReferences(array $document): array
    {
        $references = [];

        foreach (self::RESOURCE_COLLECTIONS as $collection) {
            $resources = $document[$collection] ?? [];
            if (!\is_array($resources)) {
                throw MediaException::invalidFile(self::INVALID_GLB_MESSAGE);
            }

            foreach ($resources as $index => $resource) {
                if (!\is_array($resource)) {
                    throw MediaException::invalidFile(self::INVALID_GLB_MESSAGE);
                }

                if (\array_key_exists(self::URI, $resource) && !$this->isEmbeddedDataUri($resource[self::URI])) {
                    $references[] = \sprintf('%s[%s].%s', $collection, $index, self::URI);
                }
            }
        }

        return $references;
    }

    private function isEmbeddedDataUri(mixed $uri): bool
    {
        return \is_string($uri) && str_starts_with(mb_strtolower(trim($uri)), self::DATA_URI_PREFIX);
    }
}
