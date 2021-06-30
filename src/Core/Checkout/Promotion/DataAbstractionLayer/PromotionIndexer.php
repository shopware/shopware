<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Shopware\Core\Checkout\Promotion\Event\PromotionIndexerEvent;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PromotionIndexer extends EntityIndexer
{
    public const EXCLUSION_UPDATER = 'promotion.exclusion';
    public const REDEMPTION_UPDATER = 'promotion.redemption';

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var PromotionExclusionUpdater
     */
    private $exclusionUpdater;

    /**
     * @var PromotionRedemptionUpdater
     */
    private $redemptionUpdater;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        PromotionExclusionUpdater $exclusionUpdater,
        PromotionRedemptionUpdater $redemptionUpdater,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->exclusionUpdater = $exclusionUpdater;
        $this->redemptionUpdater = $redemptionUpdater;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getName(): string
    {
        return 'promotion.indexer';
    }

    /**
     * @param array|null $offset
     *
     * @deprecated tag:v6.5.0 The parameter $offset will be native typed
     */
    public function iterate(/*?array */$offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new PromotionIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(PromotionDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        if ($this->isGeneratingIndividualCode($event)) {
            return null;
        }

        return new PromotionIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        if ($message->allow(self::EXCLUSION_UPDATER)) {
            $this->exclusionUpdater->update($ids);
        }

        if ($message->allow(self::REDEMPTION_UPDATER)) {
            $this->redemptionUpdater->update($ids, $message->getContext());
        }

        $this->eventDispatcher->dispatch(new PromotionIndexerEvent($ids, $message->getContext(), $message->getSkip()));
    }

    private function isGeneratingIndividualCode(EntityWrittenContainerEvent $event): bool
    {
        $events = $event->getEvents();

        if (!$event->getContext()->getSource() instanceof AdminApiSource || $events === null || $events->count() !== 2) {
            return false;
        }

        $promotionIndividualWrittenEvent = $event->getEventByEntityName(PromotionIndividualCodeDefinition::ENTITY_NAME);

        if ($promotionIndividualWrittenEvent === null || $promotionIndividualWrittenEvent->getName() !== 'promotion_individual_code.written') {
            return false;
        }

        $promotionWrittenEvent = $event->getEventByEntityName(PromotionDefinition::ENTITY_NAME);

        if ($promotionWrittenEvent === null || $promotionWrittenEvent->getName() !== 'promotion.written' || !empty($promotionWrittenEvent->getPayloads()[0])) {
            return false;
        }

        return true;
    }
}
