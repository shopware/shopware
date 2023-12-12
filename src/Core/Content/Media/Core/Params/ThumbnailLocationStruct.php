<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Params;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Represents a thumbnail location
 *
 * Contains all information to generate the path for a thumbnail. Typically used in the media path strategy
 * and build over the database or by the request when the media was uploaded or renamed
 *
 * @final
 */
#[Package('buyers-experience')]
class ThumbnailLocationStruct extends Struct
{
    public function __construct(
        public string $id,
        public int $width,
        public int $height,
        public MediaLocationStruct $media
    ) {
    }
}
