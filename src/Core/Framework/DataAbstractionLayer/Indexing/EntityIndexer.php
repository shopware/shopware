<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @deprecated tag:v6.5.0 - getDecorated, getTotal will be abstract with 6.5.0 and has to be implemented in all implementations
 */
abstract class EntityIndexer
{
    /**
     * Returns a unique name for this indexer. This function is used for core updates
     * if a indexer has to run after an update.
     */
    abstract public function getName(): string;

    /**
     * Called when a full entity index is required. This function should generate a list of message for all records which
     * are indexed by this indexer.
     *
     * @param array|null $offset
     *
     * @deprecated tag:v6.5.0 The parameter $offset will be native typed
     */
    abstract public function iterate(/*?array */$offset): ?EntityIndexingMessage;

    /**
     * Called when entities are updated over the DAL. This function should react to the provided entity written events
     * and generate a list of messages which has to be processed by the `handle` function over the message queue workers.
     */
    abstract public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage;

    /**
     * Called over the message queue workers. The messages are the generated messages
     * of the `self::iterate` or `self::update` functions.
     */
    abstract public function handle(EntityIndexingMessage $message): void;

    /**
     * @deprecated tag:v6.5.0 - Will be abstract with 6.5.0 and has to be implemented in all implementations
     */
    public function getTotal(): int
    {
        return 1;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be abstract with 6.5.0 and has to be implemented in all implementations
     */
    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }
}
