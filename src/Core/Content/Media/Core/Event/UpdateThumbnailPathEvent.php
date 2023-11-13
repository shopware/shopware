<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Core\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * @implements \IteratorAggregate<array-key, string>
 *
 * This event can be dispatch, to generate the path for a thumbnail afterward and store it in the database.
 * The `MediaSubscriber` will listen to this event and generate the path for the thumbnail.
 */
#[Package('core')]
class UpdateThumbnailPathEvent implements \IteratorAggregate
{
    /**
     * @param array<string> $ids
     */
    public function __construct(public readonly array $ids)
    {
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->ids);
    }
}
