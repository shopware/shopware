<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\DataAbstractionLayer;

use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater;
use Shopware\Core\Content\Newsletter\Event\NewsletterRecipientIndexerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class NewsletterRecipientIndexer extends EntityIndexer
{
    public const CUSTOMER_NEWSLETTER_SALES_CHANNELS_UPDATER = 'newsletter_recipients.customer-newsletter-sales-channels';

    private IteratorFactory $iteratorFactory;

    private EntityRepositoryInterface $repository;

    private CustomerNewsletterSalesChannelsUpdater $customerNewsletterSalesChannelsUpdater;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        CustomerNewsletterSalesChannelsUpdater $customerNewsletterSalesChannelsUpdater,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->customerNewsletterSalesChannelsUpdater = $customerNewsletterSalesChannelsUpdater;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getName(): string
    {
        return 'newsletter_recipient.indexer';
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
}
