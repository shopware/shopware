<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerBeforeDeleteSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly JsonEntityEncoder $jsonEntityEncoder
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityDeleteEvent::class => 'beforeDelete',
        ];
    }

    public function beforeDelete(EntityDeleteEvent $event): void
    {
        $context = $event->getContext();

        $ids = $event->getIds(CustomerDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return;
        }

        $source = $context->getSource();
        $salesChannelId = null;

        if ($source instanceof SalesChannelApiSource) {
            $salesChannelId = $source->getSalesChannelId();
        }

        $criteria = (new Criteria($ids))
            ->addAssociations([
                'salutation',
                'defaultBillingAddress.country',
                'defaultBillingAddress.countryState',
                'defaultBillingAddress.salutation',
                'defaultShippingAddress.country',
                'defaultShippingAddress.countryState',
                'defaultShippingAddress.salutation',
            ]);

        $customers = $this->customerRepository->search($criteria, $context)->getEntities();

        $effectiveLanguageByCustomerId = $this->resolveEffectiveLanguageIds($customers, $salesChannelId, $context);

        $event->addSuccess(function () use ($customers, $context, $salesChannelId, $criteria, $effectiveLanguageByCustomerId): void {
            foreach ($customers as $customer) {
                $languageId = $effectiveLanguageByCustomerId[$customer->getId()] ?? null;

                $salesChannelContext = $this->salesChannelContextService->get(
                    new SalesChannelContextServiceParameters(
                        $salesChannelId ?? $customer->getSalesChannelId(),
                        Random::getAlphanumericString(32),
                        $languageId,
                        null,
                        null,
                        $context,
                    )
                );

                $this->eventDispatcher->dispatch(new CustomerDeletedEvent(
                    $salesChannelContext,
                    $customer,
                    $this->jsonEntityEncoder->encode(
                        $criteria,
                        $this->customerRepository->getDefinition(),
                        $customer,
                        '/api/customer'
                    )
                ));
            }
        });
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveEffectiveLanguageIds(CustomerCollection $customers, ?string $salesChannelIdFromSource, Context $context): array
    {
        $salesChannelIdSet = [];
        $languageIdSet = [];

        foreach ($customers as $customer) {
            $scId = $salesChannelIdFromSource ?? $customer->getSalesChannelId();
            if ($scId) {
                $salesChannelIdSet[$scId] = true;
            }

            $langId = $customer->getLanguageId();
            if ($langId) {
                $languageIdSet[$langId] = true;
            }
        }

        $salesChannelIds = array_keys($salesChannelIdSet);
        $languageIds = array_keys($languageIdSet);

        $available = [];

        if ($salesChannelIds !== [] && $languageIds !== []) {
            $criteria = (new Criteria($salesChannelIds))->addAssociation('languages');
            $criteria->getAssociation('languages')
                ->addFields(['id'])
                ->addFilter(new EqualsAnyFilter('id', $languageIds));

            $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();

            foreach ($salesChannels as $salesChannel) {
                $scId = $salesChannel->getId();
                $languages = $salesChannel->getLanguages();

                if ($languages === null || $languages->count() === 0) {
                    continue;
                }

                foreach ($languages as $language) {
                    $available[$scId][$language->getId()] = true;
                }
            }
        }

        $result = [];

        foreach ($customers as $customer) {
            $customerId = $customer->getId();
            $scId = $salesChannelIdFromSource ?? $customer->getSalesChannelId();
            $langId = $customer->getLanguageId();

            if (!$scId || !$langId || !isset($available[$scId][$langId])) {
                $result[$customerId] = null;
                continue;
            }

            $result[$customerId] = $langId;
        }

        return $result;
    }
}
