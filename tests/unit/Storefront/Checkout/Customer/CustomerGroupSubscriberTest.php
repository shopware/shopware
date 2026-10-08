<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Checkout\Customer;

use Cocur\Slugify\SlugifyInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationEntity;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Checkout\Customer\CustomerGroupSubscriber;

/**
 * @internal
 */
#[CoversClass(CustomerGroupSubscriber::class)]
class CustomerGroupSubscriberTest extends TestCase
{
    public function testGeneratedUrlsAreActive(): void
    {
        $context = Context::createDefaultContext();
        $customerGroupId = Uuid::randomHex();
        $languageId = Uuid::randomHex();

        $language = new LanguageEntity();
        $language->setId($languageId);
        $language->setActive(true);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $salesChannel->setLanguages(new LanguageCollection([$language]));

        $translation = new CustomerGroupTranslationEntity();
        $translation->setId(Uuid::randomHex());
        $translation->setLanguageId($languageId);
        $translation->setRegistrationTitle('Registration');

        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setId($customerGroupId);
        $customerGroup->setRegistrationActive(true);
        $customerGroup->setRegistrationSalesChannels(new SalesChannelCollection([$salesChannel]));
        $customerGroup->setTranslations(new CustomerGroupTranslationCollection([$translation]));

        $customerGroupRepository = new StaticEntityRepository([new CustomerGroupCollection([$customerGroup])]);

        $languageRepository = new StaticEntityRepository([new LanguageCollection([$language])]);

        $persister = $this->createMock(SeoUrlPersister::class);
        $persister->expects($this->once())
            ->method('updateSeoUrls')
            ->with(
                static::isInstanceOf(Context::class),
                'frontend.account.customer-group-registration.page',
                [$customerGroupId],
                static::callback(static function (iterable $seoUrls): bool {
                    foreach ($seoUrls as $seoUrl) {
                        if ($seoUrl['isDeleted'] !== false) {
                            return false;
                        }
                    }

                    return true;
                }),
                static::isInstanceOf(SalesChannelEntity::class)
            );

        $slugify = static::createStub(SlugifyInterface::class);
        $slugify->method('slugify')->willReturn('registration');

        $subscriber = new CustomerGroupSubscriber(
            $customerGroupRepository,
            static::createStub(EntityRepository::class),
            $languageRepository,
            $persister,
            $slugify
        );

        /** @var array<string, string> $primaryKey */
        $primaryKey = ['customerGroupId' => $customerGroupId];

        $subscriber->newSalesChannelAddedToCustomerGroup(new EntityWrittenEvent(
            'customer_group_registration_sales_channels',
            [new EntityWriteResult(
                $primaryKey,
                [],
                'customer_group_registration_sales_channels',
                EntityWriteResult::OPERATION_INSERT
            )],
            $context
        ));
    }
}
