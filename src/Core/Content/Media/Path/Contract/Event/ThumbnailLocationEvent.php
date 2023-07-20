<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Contract\Event;

use Shopware\Core\Content\Media\Path\Contract\Struct\ThumbnailLocationStruct;

/**
 * @public
 *
 * The event is dispatched, when location for a thumbnail should be generated afterward and can be used
 * to extend the data which is required for this process.
 */
class ThumbnailLocationEvent
{
    /**
     * @param array<string, ThumbnailLocationStruct> $locations
     */
    public function __construct(public array $locations)
    {
    }
}
