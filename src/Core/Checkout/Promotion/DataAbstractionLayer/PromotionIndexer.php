<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\DataAbstractionLayer;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Shopware\Core\Checkout\Promotion\Event\PromotionIndexerEvent;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class PromotionIndexer extends EntityIndexer
{
    final public const EXCLUSION_UPDATER = 'promotion.exclusion';
    final public const REDEMPTION_UPDATER = 'promotion.redemption';

    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly PromotionExclusionUpdater $exclusionUpdater,
        private readonly PromotionRedemptionUpdater $redemptionUpdater,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getName(): string
    {
        return 'promotion.indexer';
    }

    /**
     * @param array<mixed>|null $offset
     */
    public function iterate(?array $offset): ?EntityIndexingMessage
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

    public function getOptions(): array
    {
        return [
            self::EXCLUSION_UPDATER,
            self::REDEMPTION_UPDATER,
        ];
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
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
