<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\DataAbstractionLayer;

use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater;
use Shopware\Core\Content\Newsletter\Event\NewsletterRecipientIndexerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('customer-order')]
class NewsletterRecipientIndexer extends EntityIndexer
{
    final public const CUSTOMER_NEWSLETTER_SALES_CHANNELS_UPDATER = 'newsletter_recipients.customer-newsletter-sales-channels';

    /**
     * @internal
     */
    public function __construct(
        private readonly IteratorFactory $iteratorFactory,
        private readonly EntityRepository $repository,
        private readonly CustomerNewsletterSalesChannelsUpdater $customerNewsletterSalesChannelsUpdater,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getName(): string
    {
        return 'newsletter_recipient.indexer';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new NewsletterRecipientIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(NewsletterRecipientDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        return new NewsletterRecipientIndexingMessage(array_values($updates), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        $ids = array_unique(array_filter($ids));

        if (empty($ids) || !$message instanceof NewsletterRecipientIndexingMessage) {
            return;
        }

        $context = $message->getContext();

        if ($message->allow(self::CUSTOMER_NEWSLETTER_SALES_CHANNELS_UPDATER)) {
            if ($message->isDeletedNewsletterRecipients()) {
                $this->customerNewsletterSalesChannelsUpdater->delete($ids);
            } else {
                $this->customerNewsletterSalesChannelsUpdater->update($ids);
            }
        }

        $this->eventDispatcher->dispatch(new NewsletterRecipientIndexerEvent($ids, $context, $message->getSkip()));
    }

    public function getOptions(): array
    {
        return [
            self::CUSTOMER_NEWSLETTER_SALES_CHANNELS_UPDATER,
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
