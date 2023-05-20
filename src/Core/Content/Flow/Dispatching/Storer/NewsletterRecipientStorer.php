<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\NewsletterRecipientAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('business-ops')]
class NewsletterRecipientStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $newsletterRecipientRepository,
        private readonly EventDispatcherInterface $dispatcher
    ) {
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
            $this->lazyLoad(...)
        );
    }

    /**
     * @param array<int, mixed> $args
     *
     * @deprecated tag:v6.6.0 - Will be removed in v6.6.0.0
     */
    public function load(array $args): ?NewsletterRecipientEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6_6_0_0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0.0')
        );

        [$id, $context] = $args;
        $criteria = new Criteria([$id]);

        return $this->loadNewsletterRecipient($criteria, $context, $id);
    }

    private function lazyLoad(StorableFlow $storableFlow): ?NewsletterRecipientEntity
    {
        $id = $storableFlow->getStore(NewsletterRecipientAware::NEWSLETTER_RECIPIENT_ID);
        if ($id === null) {
            return null;
        }

        $criteria = new Criteria([$id]);

        return $this->loadNewsletterRecipient($criteria, $storableFlow->getContext(), $id);
    }

    private function loadNewsletterRecipient(Criteria $criteria, Context $context, string $id): ?NewsletterRecipientEntity
    {
        $event = new BeforeLoadStorableFlowDataEvent(
            NewsletterRecipientDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        $newsletterRecipient = $this->newsletterRecipientRepository->search($criteria, $context)->get($id);

        if ($newsletterRecipient) {
            /** @var NewsletterRecipientEntity $newsletterRecipient */
            return $newsletterRecipient;
        }

        return null;
    }
}
