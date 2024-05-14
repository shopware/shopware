<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\DataAbstractionLayer;

use Shopware\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlUpdateListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('buyers-experience')]
class LandingPageIndexer extends EntityIndexer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getName(): string
    {
        return 'landing_page.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new LandingPageIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(LandingPageDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        return new LandingPageIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        // This indexer is only used to update the seo urls over the SeoUrlUpdater, so we only dispatch the event here
        $this->eventDispatcher->dispatch(new LandingPageIndexerEvent($ids, $message->getContext(), $message->getSkip()));
    }

    public function getOptions(): array
    {
        return [
            SeoUrlUpdateListener::LANDING_PAGE_SEO_URL_UPDATER,
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
}
