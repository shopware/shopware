<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\NewsletterRecipientAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\NewsletterRecipientProvider;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class NewsletterRecipientStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<NewsletterRecipientCollection> $newsletterRecipientRepository
     */
    public function __construct(
        private readonly EntityRepository $newsletterRecipientRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly NewsletterRecipientProvider $newsletterRecipientProvider,
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

    private function lazyLoad(StorableFlow $storableFlow): ?NewsletterRecipientEntity
    {
        $id = $storableFlow->getStore(NewsletterRecipientAware::NEWSLETTER_RECIPIENT_ID);
        if ($id === null) {
            return null;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = $this->newsletterRecipientProvider->getCriteria($id, $storableFlow->getContext());

            $event = new BeforeLoadStorableFlowDataEvent(
                NewsletterRecipientDefinition::ENTITY_NAME,
                $criteria,
                $storableFlow->getContext(),
            );

            $this->dispatcher->dispatch($event, $event->getName());

            $newsletterRecipient = $this->newsletterRecipientRepository->search($criteria, $storableFlow->getContext())->getEntities()->get($id);

            if ($newsletterRecipient) {
                return $newsletterRecipient;
            }

            return null;
        }

        return $this->newsletterRecipientProvider->getData($id, $storableFlow->getContext());
    }
}
