<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\DataAbstractionLayer;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

class ShippingMethodIndexer extends EntityIndexer
{
    /**
     * @var ShippingMethodPriceDeprecationUpdater
     */
    private $methodPriceDeprecationUpdater;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var bool
     */
    private $blueGreenEnabled;

    public function __construct(
        ShippingMethodPriceDeprecationUpdater $methodPriceDeprecationUpdater,
        IteratorFactory $iteratorFactory,
        CacheClearer $cacheClearer,
        EntityRepositoryInterface $repository,
        bool $blueGreenEnabled
    ) {
        $this->methodPriceDeprecationUpdater = $methodPriceDeprecationUpdater;
        $this->iteratorFactory = $iteratorFactory;
        $this->cacheClearer = $cacheClearer;
        $this->repository = $repository;
        $this->blueGreenEnabled = $blueGreenEnabled;
    }

    /**
     * Returns a unique name for this indexer. This function is used for core updates
     * if a indexer has to run after an update.
     */
    public function getName(): string
    {
        return 'shipping_method.indexer';
    }

    /**
     * Called when a full entity index is required. This function should generate a list of message for all records which
     * are indexed by this indexer.
     */
    public function iterate($offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new ShippingMethodIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    /**
     * Called when entities are updated over the DAL. This function should react to the provided entity written events
     * and generate a list of messages which has to be processed by the `handle` function over the message queue workers.
     */
    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $shippingMethodPriceEvent = $event->getEventByEntityName(ShippingMethodPriceDefinition::ENTITY_NAME);
        if ($shippingMethodPriceEvent === null) {
            return null;
        }

        if (!$this->blueGreenEnabled) {
            $this->methodPriceDeprecationUpdater->updateByEvent($shippingMethodPriceEvent);
        }

        return null;
    }

    /**
     * Called over the message queue workers. The messages are the generated messages
     * of the `self::iterate` or `self::update` functions.
     */
    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        if (!$this->blueGreenEnabled) {
            $this->methodPriceDeprecationUpdater->updateByShippingMethodId($ids);
        }

        $this->cacheClearer->invalidateIds($ids, ShippingMethodDefinition::ENTITY_NAME);
    }
}
