<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Contract\Service;

use Shopware\Core\Content\Media\Path\Contract\Struct\MediaLocationStruct;
use Shopware\Core\Content\Media\Path\Contract\Struct\ThumbnailLocationStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * Location builder for media path strategies
 *
 * Use this class to build the location object. When faking objects (e.g. for rename logic), you should consider to allow data extensions via events
 *
 * @public
 */
#[Package('core')]
abstract class AbstractMediaLocationBuilder
{
    /**
     * Generates a index list of media location structs
     *
     * These structs are necessary to generate the file paths for media. By default,
     * shopware stores this values inside the database to prevent unnecessary on-demand calculation
     *
     * @param array<string> $ids
     *
     * @return array<string, MediaLocationStruct> indexed by id
     */
    abstract public function media(array $ids): array;

    /**
     * Generates an index list of thumbnail location structs
     *
     * These structs are necessary to generate the file paths for thumbnails. By default
     * shopware stores this values inside the database to prevent unnecessary on-demand calculation
     *
     * @param array<string> $ids
     *
     * @return array<string, ThumbnailLocationStruct> indexed by id
     */
    abstract public function thumbnails(array $ids): array;
}
