<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('discovery')]
class RetiredMediaIndexer extends EntityIndexer
{
    public function getName(): string
    {
        return 'media.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        return null;
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
    }

    public function getTotal(): int
    {
        return 0;
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }
}
