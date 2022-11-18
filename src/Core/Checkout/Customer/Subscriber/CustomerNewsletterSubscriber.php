<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - reason:remove-subscriber - newsletter field in customer will be removed in version 6.5.0.
 * So this subscriber will be removed also on v.6.5.0
 * Please don't use this subscriber for further extensions
 *
 * @internal
 */
class CustomerNewsletterSubscriber implements EventSubscriberInterface
{
    private EntityRepository $customerRepository;

    public function __construct(EntityRepository $customerRepository)
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
        if (Feature::isActive('v6.5.0.0')) {
            return;
        }

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
        if (Feature::isActive('v6.5.0.0')) {
            return;
        }

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
