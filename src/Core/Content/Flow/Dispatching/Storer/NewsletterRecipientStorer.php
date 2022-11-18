<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\NewsletterRecipientAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;

class NewsletterRecipientStorer extends FlowStorer
{
    private EntityRepository $newsletterRecipientRepository;

    /**
     * @internal
     */
    public function __construct(EntityRepository $newsletterRecipientRepository)
    {
        $this->newsletterRecipientRepository = $newsletterRecipientRepository;
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof NewsletterRecipientAware || isset($stored[NewsletterRecipientAware::NEWSLETTER_RECIPIENT_ID])) {
            return $stored;
        }

        $stored[NewsletterRecipientAware::NEWSLETTER_RECIPIENT_ID] = $event->getNewsletterRecipientId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(NewsletterRecipientAware::NEWSLETTER_RECIPIENT_ID)) {
            return;
        }

        $storable->lazy(
            NewsletterRecipientAware::NEWSLETTER_RECIPIENT,
            [$this, 'load'],
            [$storable->getStore(NewsletterRecipientAware::NEWSLETTER_RECIPIENT_ID), $storable->getContext()]
        );
    }

    /**
     * @param array<int, mixed> $args
     */
    public function load(array $args): ?NewsletterRecipientEntity
    {
        list($id, $context) = $args;
        $criteria = new Criteria([$id]);

        $newsletterRecipient = $this->newsletterRecipientRepository->search($criteria, $context)->get($id);

        if ($newsletterRecipient) {
            /** @var NewsletterRecipientEntity $newsletterRecipient */
            return $newsletterRecipient;
        }

        return null;
    }
}
