<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @deprecated tag:v6.8.0 - Thumbnail reads no longer require an index. Remove calls to this indexer.
 */
#[Package('discovery')]
class MediaIndexer extends EntityIndexer
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

    /**
     * @phpstan-ignore shopware.deprecatedClass (The registry calls this method during normal indexing.)
     */
    public function getName(): string
    {
        return 'media.indexer';
    }

    /**
     * @phpstan-ignore shopware.deprecatedClass (The registry calls this method during normal indexing.)
     */
    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        return null;
    }

    /**
     * @phpstan-ignore shopware.deprecatedClass (The registry calls this method during normal indexing.)
     */
    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        return null;
    }

    /**
     * @phpstan-ignore shopware.deprecatedClass (The registry calls this method during normal indexing.)
     */
    public function handle(EntityIndexingMessage $message): void
    {
    }

    /**
     * @phpstan-ignore shopware.deprecatedClass (The registry calls this method during normal indexing.)
     */
    public function getTotal(): int
    {
        return 0;
    }

    public function getDecorated(): EntityIndexer
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Decorating the media indexer is deprecated.');

        throw new DecorationPatternException(static::class);
    }
}
