<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @feature-deprecated tag:v6.5.0 (FEATURE_NEXT_16106) - newsletter field in customer will be remove on version 6.5.0.
 * So this subscriber will be removed also on v.6.5.0
 * Please don't use this subscriber for further extensions
 *
 * @internal
 */
class CustomerNewsletterSubscriber implements EventSubscriberInterface
{
    private EntityRepositoryInterface $customerRepository;

    public function __construct(EntityRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NewsletterConfirmEvent::class => 'newsletterConfirmed',
            NewsletterUnsubscribeEvent::class => 'newsletterUnsubscribed',
        ];
    }

    public function newsletterConfirmed(NewsletterConfirmEvent $event): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $event->getNewsletterRecipient()->getEmail()));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $event->getSalesChannelId()));
        $customerIdSearchResult = $this->customerRepository->searchIds($criteria, $event->getContext());
        if ($customerIdSearchResult->getTotal() === 1) {
            $this->customerRepository->update(
                [
                    [
                        'id' => $customerIdSearchResult->firstId(),
                        'newsletter' => true,
                    ],
                ],
                $event->getContext()
            );
        }
    }

    public function newsletterUnsubscribed(NewsletterUnsubscribeEvent $event): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $event->getNewsletterRecipient()->getEmail()));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $event->getSalesChannelId()));
        $customerIdSearchResult = $this->customerRepository->searchIds($criteria, $event->getContext());
        if ($customerIdSearchResult->getTotal() === 1) {
            $this->customerRepository->update(
                [
                    [
                        'id' => $customerIdSearchResult->firstId(),
                        'newsletter' => false,
                    ],
                ],
                $event->getContext()
            );
        }
    }
}
