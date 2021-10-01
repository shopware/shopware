<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Cocur\Slugify\SlugifyInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationCollection;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerGroupSubscriber implements EventSubscriberInterface
{
    private const ROUTE_NAME = 'frontend.account.customer-group-registration.page';

    private EntityRepositoryInterface $customerGroupRepository;

    private SeoUrlPersister $persister;

    private SlugifyInterface $slugify;

    private EntityRepositoryInterface $seoUrlRepository;

    private EntityRepositoryInterface $languageRepository;

    public function __construct(
        EntityRepositoryInterface $customerGroupRepository,
        EntityRepositoryInterface $seoUrlRepository,
        EntityRepositoryInterface $languageRepository,
        SeoUrlPersister $persister,
        SlugifyInterface $slugify
    ) {
        $this->customerGroupRepository = $customerGroupRepository;
        $this->seoUrlRepository = $seoUrlRepository;
        $this->persister = $persister;
        $this->slugify = $slugify;
        $this->languageRepository = $languageRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'customer_group_translation.written' => 'updatedCustomerGroup',
            'customer_group_registration_sales_channels.written' => 'newSalesChannelAddedToCustomerGroup',
            'customer_group_translation.deleted' => 'deleteCustomerGroup',
        ];
    }

    public function newSalesChannelAddedToCustomerGroup(EntityWrittenEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $ids[] = $writeResult->getPrimaryKey()['customerGroupId'];
        }

        if (\count($ids) === 0) {
            return;
        }

        $this->createUrls($ids, $event->getContext());
    }

    public function updatedCustomerGroup(EntityWrittenEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->hasPayload('registrationTitle')) {
                $ids[] = $writeResult->getPrimaryKey()['customerGroupId'];
            }
        }

        if (\count($ids) === 0) {
            return;
        }

        $this->createUrls($ids, $event->getContext());
    }

    public function deleteCustomerGroup(EntityDeletedEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $ids[] = $writeResult->getPrimaryKey()['customerGroupId'];
        }

        if (\count($ids) === 0) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('foreignKey', $ids));
        $criteria->addFilter(new EqualsFilter('routeName', self::ROUTE_NAME));

        $ids = array_values($this->seoUrlRepository->searchIds($criteria, $event->getContext())->getIds());

        if (\count($ids) === 0) {
            return;
        }

        $this->seoUrlRepository->delete(array_map(function (string $id) {
            return ['id' => $id];
        }, $ids), $event->getContext());
    }

    private function createUrls(array $ids, Context $context): void
    {
        $criteria = new Criteria($ids);
        $criteria->addFilter(new EqualsFilter('registrationActive', true));
        $criteria->addAssociation('registrationSalesChannels.languages');
        $criteria->addAssociation('translations');

        /** @var CustomerGroupCollection $groups */
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

                $languageIds = $registrationSalesChannel->getLanguages()->getIds();
                $criteria = new Criteria($languageIds);
                $languageCollection = $this->languageRepository->search($criteria, $context)->getEntities();

                foreach ($languageIds as $languageId) {
                    $title = $this->getTranslatedTitle($group->getTranslations(), $languageCollection->get($languageId));

                    if (!isset($buildUrls[$languageId])) {
                        $buildUrls[$languageId] = [
                            'urls' => [],
                            'salesChannel' => $registrationSalesChannel,
                        ];
                    }

                    $buildUrls[$languageId]['urls'][] = [
                        'salesChannelId' => $registrationSalesChannel->getId(),
                        'foreignKey' => $group->getId(),
                        'routeName' => self::ROUTE_NAME,
                        'pathInfo' => '/customer-group-registration/' . $group->getId(),
                        'isCanonical' => true,
                        'seoPathInfo' => '/' . $this->slugify->slugify($title),
                    ];
                }
            }
        }

        foreach ($buildUrls as $languageId => $config) {
            $context = new Context(
                $context->getSource(),
                $context->getRuleIds(),
                $context->getCurrencyId(),
                [$languageId]
            );

            $this->persister->updateSeoUrls(
                $context,
                self::ROUTE_NAME,
                array_column($config['urls'], 'foreignKey'),
                $config['urls'],
                $config['salesChannel']
            );
        }
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
