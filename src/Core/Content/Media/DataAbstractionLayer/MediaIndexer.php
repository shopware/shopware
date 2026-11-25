<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Thumbnail reads no longer require an index. Remove calls to this indexer.
 */
#[Package('discovery')]
class MediaIndexer extends RetiredMediaIndexer
{
    /**
     * @internal
     */
    public function __construct(mixed ...$obsoleteDependencies)
    {
        if ($obsoleteDependencies !== []) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'The media indexer is obsolete. Read the thumbnails association instead.');
        }
    }
}
