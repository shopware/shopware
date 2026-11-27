<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin\Format;

use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Framework\Log\Package;

/**
 * Collects the provenance metadata of one image format, so that the AI origin processors can evaluate it.
 * Register an implementation with the `shopware.media.ai_origin.format_reader` tag.
 * Readers with a higher tag priority are asked first, and the first that supports the file extension reads the file.
 * Files of other formats only get a search for an XMP packet and their EXIF tags.
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
interface ImageFormatReader
{
    /**
     * @param string $fileExtension lower case, without the dot
     */
    public function supports(string $fileExtension): bool;

    public function read(string $bytes): ImageProvenance;
}
