<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('buyers-experience')]
class UnusedMediaSearchStartEvent
{
    public function __construct(public int $totalMedia, public int $totalMediaDeletionCandidates)
    {
    }
}
