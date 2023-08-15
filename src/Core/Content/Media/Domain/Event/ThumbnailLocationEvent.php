<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Domain\Event;

use Shopware\Core\Content\Media\Domain\Path\Struct\ThumbnailLocationStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * The event is dispatched, when location for a thumbnail should be generated afterward and can be used
 * to extend the data which is required for this process.
 *
 * @implements \IteratorAggregate<array-key, ThumbnailLocationStruct>
 */
#[Package('content')]
class ThumbnailLocationEvent implements \IteratorAggregate
{
    /**
     * @param array<string, ThumbnailLocationStruct> $locations
     */
    public function __construct(public array $locations)
    {
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->locations);
    }
}
