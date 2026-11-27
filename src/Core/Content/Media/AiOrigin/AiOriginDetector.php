<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin;

use Shopware\Core\Content\Media\AiOrigin\Format\ImageFormatReader;
use Shopware\Core\Content\Media\AiOrigin\Processor\AiOriginProcessorInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Decides whether an image file was made or changed with generative AI.
 *
 * The format reader for the file extension collects the metadata of the file once into an {@see ImageProvenance},
 * then the processors are asked in priority order. Containers are read without an image library,
 * because GD drops all metadata and Imagick is optional.
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
class AiOriginDetector
{
    /**
     * @param iterable<ImageFormatReader> $formatReaders
     * @param iterable<AiOriginProcessorInterface> $processors
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly iterable $formatReaders,
        private readonly iterable $processors,
        private readonly ExifReader $exifReader,
    ) {
    }

    public function detect(string $filePath, string $fileExtension): ?MediaAiOrigin
    {
        try {
            $provenance = $this->read($this->filesystem->readFile($filePath), strtolower($fileExtension));
        } catch (IOException) {
            return null;
        }

        if ($provenance->isEmpty()) {
            return null;
        }

        foreach ($this->processors as $processor) {
            $origin = $processor->process($provenance);
            if ($origin !== null) {
                return $origin;
            }
        }

        return null;
    }

    private function read(string $bytes, string $fileExtension): ImageProvenance
    {
        foreach ($this->formatReaders as $formatReader) {
            if ($formatReader->supports($fileExtension)) {
                return $formatReader->read($bytes);
            }
        }

        // other formats, for example GIF: XMP is plain text in every format
        $xmp = (new ByteReader($bytes))->findEnclosed('<x:xmpmeta', '</x:xmpmeta>');

        return new ImageProvenance(xmpPackets: $xmp === null ? [] : [$xmp], exif: $this->exifReader->read($bytes));
    }
}
