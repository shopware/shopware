<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Customer;

use Cocur\Slugify\SlugifyInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Tests\Integration\Storefront\Checkout\Customer\CustomerGroupSubscriberTest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see CustomerGroupSubscriberTest
 */
#[Package('checkout')]
class CustomerGroupSubscriber implements EventSubscriberInterface
{
    private const ROUTE_NAME = 'frontend.account.customer-group-registration.page';

    private const HEADLESS_ROUTE_NAME = 'store-api.customer-group-registration';

    /**
     * @internal
     *
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $customerGroupRepository,
        private readonly EntityRepository $seoUrlRepository,
        private readonly EntityRepository $languageRepository,
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

        $this->createUrls($ids, $event->getContext());
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

        $this->createUrls($ids, $event->getContext());
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
            ->addFilter(new EqualsAnyFilter('routeName', [self::ROUTE_NAME, self::HEADLESS_ROUTE_NAME]));

        $ids = $this->seoUrlRepository->searchIds($criteria, $event->getContext())->getIds();

        if ($ids === []) {
            return;
        }

        $this->seoUrlRepository->delete(array_map(static fn (string $id) => ['id' => $id], $ids), $event->getContext());
    }

    /**
     * @param list<string> $ids
     */
    private function createUrls(array $ids, Context $context): void
    {
        $criteria = (new Criteria($ids))
            ->addFilter(new EqualsFilter('registrationActive', true))
            ->addAssociations(['registrationSalesChannels.languages', 'registrationSalesChannels.domains', 'translations']);

        $groups = $this->customerGroupRepository->search($criteria, $context)->getEntities();
        $buildUrls = [];

        foreach ($groups as $group) {
            if ($group->getRegistrationSalesChannels() === null) {
                continue;
            }

            foreach ($group->getRegistrationSalesChannels() as $registrationSalesChannel) {
                if ($registrationSalesChannel->getLanguages() === null) {
                    continue;
                }

                $isHeadless = $registrationSalesChannel->getTypeId() === Defaults::SALES_CHANNEL_TYPE_API;
                $routeName = $isHeadless ? self::HEADLESS_ROUTE_NAME : self::ROUTE_NAME;

                $languageIds = $registrationSalesChannel->getLanguages()->getIds();
                $languageCriteria = new Criteria($languageIds);
                $languageCriteria->addFilter(new EqualsFilter('active', true));

                $languageCollection = $this->languageRepository->search($languageCriteria, $context)->getEntities();

                foreach ($languageIds as $languageId) {
                    $language = $languageCollection->get($languageId);
                    if (!$language) {
                        continue;
                    }

                    // headless SEO URLs are only created for languages with an external storefront domain,
                    // mirroring the SEO URL generation of products, categories and landing pages
                    if ($isHeadless && !$this->hasExternalStorefrontDomain($registrationSalesChannel, $languageId)) {
                        continue;
                    }

                    $title = $this->getTranslatedTitle($group->getTranslations(), $language);

                    if ($title === '') {
                        continue;
                    }

                    $buildKey = $languageId . '-' . $routeName;

                    if (!isset($buildUrls[$buildKey])) {
                        $buildUrls[$buildKey] = [
                            'languageId' => $languageId,
                            'routeName' => $routeName,
                            'urls' => [],
                            'salesChannel' => $registrationSalesChannel,
                        ];
                    }

                    $buildUrls[$buildKey]['urls'][] = [
                        'salesChannelId' => $registrationSalesChannel->getId(),
                        'foreignKey' => $group->getId(),
                        'routeName' => $routeName,
                        'pathInfo' => '/customer-group-registration/' . $group->getId(),
                        'isCanonical' => true,
                        'isDeleted' => false,
                        'seoPathInfo' => '/' . $this->slugify->slugify($title),
                    ];
                }
            }
        }

        foreach ($buildUrls as $config) {
            $context = new Context(
                $context->getSource(),
                $context->getRuleIds(),
                $context->getCurrencyId(),
                [$config['languageId']]
            );

            $this->persister->updateSeoUrls(
                $context,
                $config['routeName'],
                array_column($config['urls'], 'foreignKey'),
                $config['urls'],
                $config['salesChannel']
            );
        }
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
