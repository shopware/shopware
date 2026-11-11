<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Cocur\Slugify\SlugifyInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Maintains the SEO URLs of customer group registration forms for headless (API type)
 * sales channels with an external storefront domain. The storefront counterpart lives in
 * the Storefront bundle (CustomerGroupSubscriber), so headless setups without the
 * Storefront bundle still get their rows.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber\CustomerGroupRegistrationSeoUrlSubscriberTest
 */
#[Package('checkout')]
class CustomerGroupRegistrationSeoUrlSubscriber implements EventSubscriberInterface
{
    private const ROUTE_NAME = 'store-api.customer-group-registration.config';

    /**
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     */
    public function __construct(
        private readonly EntityRepository $customerGroupRepository,
        private readonly EntityRepository $seoUrlRepository,
        private readonly SeoUrlPersister $persister,
        private readonly SlugifyInterface $slugify
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'customer_group_translation.written' => 'updatedCustomerGroup',
            'customer_group_registration_sales_channels.written' => 'newSalesChannelAddedToCustomerGroup',
            'customer_group_translation.deleted' => 'deleteCustomerGroup',
            'sales_channel_domain.written' => 'updatedSalesChannelDomain',
            'sales_channel_domain.deleted' => 'updatedSalesChannelDomain',
            EntityWriteEvent::class => 'requestSalesChannelDomainChangeSet',
        ];
    }

    /**
     * @param EntityWrittenEvent<array<string, string>> $event
     */
    public function newSalesChannelAddedToCustomerGroup(EntityWrittenEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $pk = $writeResult->getPrimaryKey();
            $ids[] = $pk['customerGroupId'];
        }

        if ($ids === []) {
            return;
        }

        $this->updateSeoUrls($ids, $event->getContext());
    }

    /**
     * @param EntityWrittenEvent<array<string, string>> $event
     */
    public function updatedCustomerGroup(EntityWrittenEvent $event): void
    {
        $ids = [];

        foreach ($event->getResults()->withPayloadProperties('registrationTitle') as $writeResult) {
            $pk = $writeResult->getPrimaryKey();
            $ids[] = $pk['customerGroupId'];
        }

        if ($ids === []) {
            return;
        }

        $this->updateSeoUrls($ids, $event->getContext());
    }

    /**
     * @param EntityDeletedEvent<array<string, string>> $event
     */
    public function deleteCustomerGroup(EntityDeletedEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $pk = $writeResult->getPrimaryKey();
            $ids[] = $pk['customerGroupId'];
        }

        if ($ids === []) {
            return;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsAnyFilter('foreignKey', $ids))
            ->addFilter(new EqualsFilter('routeName', self::ROUTE_NAME));

        $ids = $this->seoUrlRepository->searchIds($criteria, $event->getContext())->getPrimaryKeyData();
        if ($ids === []) {
            return;
        }

        $this->seoUrlRepository->delete($ids, $event->getContext());
    }

    /**
     * Updated and deleted domains only carry their sales channel in the change set
     */
    public function requestSalesChannelDomainChangeSet(EntityWriteEvent $event): void
    {
        foreach ($event->getCommandsForEntity(SalesChannelDomainDefinition::ENTITY_NAME) as $command) {
            if ($command instanceof ChangeSetAware) {
                $command->requestChangeSet();
            }
        }
    }

    /**
     * Domain changes (adding or removing domains, toggling the external storefront flag)
     * change which rows may exist, so the rows of the route in the sales channels of the
     * written domains are recomputed and rows that no longer qualify are removed.
     *
     * @param EntityWrittenEvent<array<string, string>> $event
     */
    public function updatedSalesChannelDomain(EntityWrittenEvent $event): void
    {
        $salesChannelIds = $this->getSalesChannelIdsOfDomains($event);

        if ($salesChannelIds === null) {
            $this->updateSeoUrls(null, $event->getContext());

            return;
        }

        if ($salesChannelIds === []) {
            return;
        }

        $this->updateSeoUrls(null, $event->getContext(), $salesChannelIds);
    }

    /**
     * @param list<string>|null $ids null recomputes all customer groups with an active registration
     * @param list<string>|null $salesChannelIds only recomputes the rows of these sales channels and removes their obsolete rows
     */
    private function updateSeoUrls(?array $ids, Context $context, ?array $salesChannelIds = null): void
    {
        $criteria = ($ids === null ? new Criteria() : new Criteria($ids))
            ->addFilter(new EqualsFilter('registrationActive', true))
            ->addAssociations(['registrationSalesChannels.languages', 'registrationSalesChannels.domains', 'translations']);

        $criteria->getAssociation('registrationSalesChannels.languages')
            ->addFilter(new EqualsFilter('active', true));

        if ($salesChannelIds !== null) {
            $criteria->addFilter(new EqualsAnyFilter('registrationSalesChannels.id', $salesChannelIds));
            $criteria->getAssociation('registrationSalesChannels')->addFilter(new EqualsAnyFilter('id', $salesChannelIds));
        }

        $groups = $this->customerGroupRepository->search($criteria, $context)->getEntities();
        $buildUrls = [];

        foreach ($groups as $group) {
            if ($group->getRegistrationSalesChannels() === null) {
                continue;
            }

            foreach ($group->getRegistrationSalesChannels() as $registrationSalesChannel) {
                if ($registrationSalesChannel->getTypeId() !== Defaults::SALES_CHANNEL_TYPE_API) {
                    continue;
                }

                if ($registrationSalesChannel->getLanguages() === null) {
                    continue;
                }

                foreach ($registrationSalesChannel->getLanguages() as $languageId => $language) {
                    // rows are only created for languages with an external storefront domain,
                    // mirroring the SEO URL generation of products, categories and landing pages
                    if (!$this->hasExternalStorefrontDomain($registrationSalesChannel, $languageId)) {
                        continue;
                    }

                    $title = $this->getTranslatedTitle($group->getTranslations(), $language);

                    if ($title === '') {
                        continue;
                    }

                    if (!isset($buildUrls[$languageId])) {
                        $buildUrls[$languageId] = [
                            'languageId' => $languageId,
                            'urls' => [],
                            'salesChannel' => $registrationSalesChannel,
                        ];
                    }

                    $buildUrls[$languageId]['urls'][] = [
                        'salesChannelId' => $registrationSalesChannel->getId(),
                        'foreignKey' => $group->getId(),
                        'routeName' => self::ROUTE_NAME,
                        'pathInfo' => '/store-api/customer-group-registration/config/' . $group->getId(),
                        'isCanonical' => true,
                        'isDeleted' => false,
                        'seoPathInfo' => '/' . $this->slugify->slugify($title),
                    ];
                }
            }
        }

        foreach ($buildUrls as $config) {
            $languageContext = new Context(
                $context->getSource(),
                $context->getRuleIds(),
                $context->getCurrencyId(),
                [$config['languageId']]
            );

            $this->persister->updateSeoUrls(
                $languageContext,
                self::ROUTE_NAME,
                array_column($config['urls'], 'foreignKey'),
                $config['urls'],
                $config['salesChannel']
            );
        }

        if ($ids === null) {
            $this->removeObsoleteSeoUrls($buildUrls, $context, $salesChannelIds);
        }
    }

    /**
     * @param EntityWrittenEvent<array<string, string>> $event
     *
     * @return list<string>|null null if the sales channel of a domain is unknown
     */
    private function getSalesChannelIdsOfDomains(EntityWrittenEvent $event): ?array
    {
        $salesChannelIds = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $found = false;

            $salesChannelId = $writeResult->getPayload()['salesChannelId'] ?? null;
            if (\is_string($salesChannelId)) {
                $salesChannelIds[$salesChannelId] = $salesChannelId;
                $found = true;
            }

            // the previous sales channel of updated (moved) and deleted domains
            $previousSalesChannelId = $writeResult->getChangeSet()?->getBefore('sales_channel_id');
            if (\is_string($previousSalesChannelId)) {
                $previousSalesChannelId = bin2hex($previousSalesChannelId);
                $salesChannelIds[$previousSalesChannelId] = $previousSalesChannelId;
                $found = true;
            }

            if (!$found) {
                return null;
            }
        }

        return array_values($salesChannelIds);
    }

    /**
     * @param array<string, array{languageId: string, urls: list<array<string, mixed>>, salesChannel: SalesChannelEntity}> $buildUrls
     * @param list<string>|null $salesChannelIds null checks the rows of all sales channels
     */
    private function removeObsoleteSeoUrls(array $buildUrls, Context $context, ?array $salesChannelIds = null): void
    {
        $validKeys = [];

        foreach ($buildUrls as $config) {
            foreach ($config['urls'] as $url) {
                $validKeys[$url['salesChannelId'] . '-' . $config['languageId'] . '-' . $url['foreignKey']] = true;
            }
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('routeName', self::ROUTE_NAME));

        if ($salesChannelIds !== null) {
            $criteria->addFilter(new EqualsAnyFilter('salesChannelId', $salesChannelIds));
        }

        $obsoleteIds = [];

        foreach ($this->seoUrlRepository->search($criteria, $context)->getEntities() as $seoUrl) {
            $key = $seoUrl->getSalesChannelId() . '-' . $seoUrl->getLanguageId() . '-' . $seoUrl->getForeignKey();

            if (!isset($validKeys[$key])) {
                $obsoleteIds[] = ['id' => $seoUrl->getId()];
            }
        }

        if ($obsoleteIds === []) {
            return;
        }

        $this->seoUrlRepository->delete($obsoleteIds, $context);
    }

    private function hasExternalStorefrontDomain(SalesChannelEntity $salesChannel, string $languageId): bool
    {
        $domains = $salesChannel->getDomains();
        if ($domains === null) {
            return false;
        }

        foreach ($domains as $domain) {
            if ($domain->getLanguageId() === $languageId && $domain->getIsExternalStorefront()) {
                return true;
            }
        }

        return false;
    }

    private function getTranslatedTitle(?CustomerGroupTranslationCollection $translations, LanguageEntity $language): string
    {
        if ($translations === null) {
            return '';
        }

        // Requested translation
        foreach ($translations as $translation) {
            if ($translation->getLanguageId() === $language->getId() && $translation->getRegistrationTitle() !== null) {
                return $translation->getRegistrationTitle();
            }
        }

        // Inherited translation
        foreach ($translations as $translation) {
            if ($translation->getLanguageId() === $language->getParentId() && $translation->getRegistrationTitle() !== null) {
                return $translation->getRegistrationTitle();
            }
        }

        // System Language
        foreach ($translations as $translation) {
            if ($translation->getLanguageId() === Defaults::LANGUAGE_SYSTEM && $translation->getRegistrationTitle() !== null) {
                return $translation->getRegistrationTitle();
            }
        }

        return '';
    }
}
