<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterEntity;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelEntity::class)]
class SalesChannelEntityTest extends TestCase
{
    public function testGetSetMaintenanceIpAllowlist(): void
    {
        $entity = new SalesChannelEntity();
        static::assertNull($entity->getMaintenanceIpAllowlist());

        $entity->setMaintenanceIpAllowlist(['127.0.0.1', '::1']);

        static::assertSame(['127.0.0.1', '::1'], $entity->getMaintenanceIpAllowlist());
    }

    public function testDeprecatedGetterReturnsAllowlistValue(): void
    {
        $entity = new SalesChannelEntity();
        $entity->setMaintenanceIpAllowlist(['192.168.0.1']);

        $result = Feature::silent('v6.8.0.0', fn (): ?array => $entity->getMaintenanceIpWhitelist());

        static::assertSame(['192.168.0.1'], $result);
    }

    public function testDeprecatedSetterUpdatesAllowlist(): void
    {
        $entity = new SalesChannelEntity();

        Feature::silent('v6.8.0.0', function () use ($entity): void {
            $entity->setMaintenanceIpWhitelist(['10.0.0.1']);
        });

        static::assertSame(['10.0.0.1'], $entity->getMaintenanceIpAllowlist());
    }

    public function testDeprecatedGetterThrowsWhenMajorIsActive(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('The deprecation only throws while the v6.8.0.0 feature flag is active.');
        }

        $this->expectException(\Throwable::class);

        (new SalesChannelEntity())->getMaintenanceIpWhitelist();
    }

    public function testScalarAccessorsRoundTrip(): void
    {
        $salesChannel = new SalesChannelEntity();

        $salesChannel->setMailHeaderFooterId('mail-header-footer-id');
        $salesChannel->setLanguageId('language-id');
        $salesChannel->setCurrencyId('currency-id');
        $salesChannel->setPaymentMethodId('payment-method-id');
        $salesChannel->setShippingMethodId('shipping-method-id');
        $salesChannel->setCountryId('country-id');
        $salesChannel->setName('name');
        $salesChannel->setShortName('short-name');
        $salesChannel->setAccessKey('access-key');
        $salesChannel->setConfiguration(['configuration']);
        $salesChannel->setActive(true);
        $salesChannel->setMaintenance(true);
        $salesChannel->setTypeId('type-id');
        $salesChannel->setNavigationCategoryId('navigation-category-id');
        $salesChannel->setHomeSlotConfig(['slot-id' => ['content' => 'home']]);
        $salesChannel->setHomeCmsPageId('home-cms-page-id');
        $salesChannel->setHomeEnabled(true);
        $salesChannel->setHomeName('home-name');
        $salesChannel->setHomeMetaTitle('home-meta-title');
        $salesChannel->setHomeMetaDescription('home-meta-description');
        $salesChannel->setHomeKeywords('home-keywords');
        $salesChannel->setCustomerGroupId('customer-group-id');
        $salesChannel->setFooterCategoryId('footer-category-id');
        $salesChannel->setServiceCategoryId('service-category-id');
        $salesChannel->setPaymentMethodIds(['paymentMethodIds']);
        $salesChannel->setNavigationCategoryDepth(3);
        $salesChannel->setHreflangActive(true);
        $salesChannel->setHreflangDefaultDomainId('hreflang-default-domain-id');
        $salesChannel->setAnalyticsId('analytics-id');
        $salesChannel->setTaxCalculationType('tax-calculation-type');
        $salesChannel->setNavigationCategoryVersionId('navigation-category-version-id');
        $salesChannel->setHomeCmsPageVersionId('home-cms-page-version-id');
        $salesChannel->setFooterCategoryVersionId('footer-category-version-id');
        $salesChannel->setServiceCategoryVersionId('service-category-version-id');
        $salesChannel->setBusinessTimeZone('business-time-zone');

        static::assertSame('mail-header-footer-id', $salesChannel->getMailHeaderFooterId());
        static::assertSame('language-id', $salesChannel->getLanguageId());
        static::assertSame('currency-id', $salesChannel->getCurrencyId());
        static::assertSame('payment-method-id', $salesChannel->getPaymentMethodId());
        static::assertSame('shipping-method-id', $salesChannel->getShippingMethodId());
        static::assertSame('country-id', $salesChannel->getCountryId());
        static::assertSame('name', $salesChannel->getName());
        static::assertSame('short-name', $salesChannel->getShortName());
        static::assertSame('access-key', $salesChannel->getAccessKey());
        static::assertSame(['configuration'], $salesChannel->getConfiguration());
        static::assertTrue($salesChannel->getActive());
        static::assertTrue($salesChannel->isMaintenance());
        static::assertSame('type-id', $salesChannel->getTypeId());
        static::assertSame('navigation-category-id', $salesChannel->getNavigationCategoryId());
        static::assertSame(['slot-id' => ['content' => 'home']], $salesChannel->getHomeSlotConfig());
        static::assertSame('home-cms-page-id', $salesChannel->getHomeCmsPageId());
        static::assertTrue($salesChannel->getHomeEnabled());
        static::assertSame('home-name', $salesChannel->getHomeName());
        static::assertSame('home-meta-title', $salesChannel->getHomeMetaTitle());
        static::assertSame('home-meta-description', $salesChannel->getHomeMetaDescription());
        static::assertSame('home-keywords', $salesChannel->getHomeKeywords());
        static::assertSame('customer-group-id', $salesChannel->getCustomerGroupId());
        static::assertSame('footer-category-id', $salesChannel->getFooterCategoryId());
        static::assertSame('service-category-id', $salesChannel->getServiceCategoryId());
        static::assertSame(['paymentMethodIds'], $salesChannel->getPaymentMethodIds());
        static::assertSame(3, $salesChannel->getNavigationCategoryDepth());
        static::assertTrue($salesChannel->isHreflangActive());
        static::assertSame('hreflang-default-domain-id', $salesChannel->getHreflangDefaultDomainId());
        static::assertSame('analytics-id', $salesChannel->getAnalyticsId());
        static::assertSame('tax-calculation-type', $salesChannel->getTaxCalculationType());
        static::assertSame('navigation-category-version-id', $salesChannel->getNavigationCategoryVersionId());
        static::assertSame('home-cms-page-version-id', $salesChannel->getHomeCmsPageVersionId());
        static::assertSame('footer-category-version-id', $salesChannel->getFooterCategoryVersionId());
        static::assertSame('service-category-version-id', $salesChannel->getServiceCategoryVersionId());
        static::assertSame('business-time-zone', $salesChannel->getBusinessTimeZone());
    }

    public function testAssociationAccessorsRoundTrip(): void
    {
        $salesChannel = new SalesChannelEntity();

        $mailHeaderFooter = new MailHeaderFooterEntity();
        $currencies = new CurrencyCollection();
        $languages = new LanguageCollection();
        $currency = new CurrencyEntity();
        $language = new LanguageEntity();
        $paymentMethod = new PaymentMethodEntity();
        $shippingMethod = new ShippingMethodEntity();
        $country = new CountryEntity();
        $orders = new OrderCollection();
        $customers = new CustomerCollection();
        $type = new SalesChannelTypeEntity();
        $countries = new CountryCollection();
        $translations = new SalesChannelTranslationCollection();
        $paymentMethods = new PaymentMethodCollection();
        $shippingMethods = new ShippingMethodCollection();
        $domains = new SalesChannelDomainCollection();
        $systemConfigs = new SystemConfigCollection();
        $navigationCategory = new CategoryEntity();
        $homeCmsPage = new CmsPageEntity();
        $productVisibilities = new ProductVisibilityCollection();
        $customerGroup = new CustomerGroupEntity();
        $newsletterRecipients = new NewsletterRecipientCollection();
        $promotionSalesChannels = new PromotionSalesChannelCollection();
        $numberRangeSalesChannels = new NumberRangeSalesChannelCollection();
        $footerCategory = new CategoryEntity();
        $serviceCategory = new CategoryEntity();
        $documentBaseConfigSalesChannels = new DocumentBaseConfigDefinition();
        $productReviews = new ProductReviewCollection();
        $seoUrls = new SeoUrlCollection();
        $seoUrlTemplates = new SeoUrlTemplateCollection();
        $mainCategories = new MainCategoryCollection();
        $productExports = new ProductExportCollection();
        $salesChannelFiles = new SalesChannelFileCollection();
        $hreflangDefaultDomain = new SalesChannelDomainEntity();
        $analytics = new SalesChannelAnalyticsEntity();
        $customerGroupsRegistrations = new CustomerGroupCollection();
        $boundCustomers = new CustomerCollection();
        $wishlists = new CustomerWishlistCollection();
        $landingPages = new LandingPageCollection();
        $measurementUnits = new MeasurementUnits('metric', ['length' => 'mm']);

        $salesChannel->setMailHeaderFooter($mailHeaderFooter);
        $salesChannel->setCurrencies($currencies);
        $salesChannel->setLanguages($languages);
        $salesChannel->setCurrency($currency);
        $salesChannel->setLanguage($language);
        $salesChannel->setPaymentMethod($paymentMethod);
        $salesChannel->setShippingMethod($shippingMethod);
        $salesChannel->setCountry($country);
        $salesChannel->setOrders($orders);
        $salesChannel->setCustomers($customers);
        $salesChannel->setType($type);
        $salesChannel->setCountries($countries);
        $salesChannel->setTranslations($translations);
        $salesChannel->setPaymentMethods($paymentMethods);
        $salesChannel->setShippingMethods($shippingMethods);
        $salesChannel->setDomains($domains);
        $salesChannel->setSystemConfigs($systemConfigs);
        $salesChannel->setNavigationCategory($navigationCategory);
        $salesChannel->setHomeCmsPage($homeCmsPage);
        $salesChannel->setProductVisibilities($productVisibilities);
        $salesChannel->setCustomerGroup($customerGroup);
        $salesChannel->setNewsletterRecipients($newsletterRecipients);
        $salesChannel->setPromotionSalesChannels($promotionSalesChannels);
        $salesChannel->setNumberRangeSalesChannels($numberRangeSalesChannels);
        $salesChannel->setFooterCategory($footerCategory);
        $salesChannel->setServiceCategory($serviceCategory);
        $salesChannel->setDocumentBaseConfigSalesChannels($documentBaseConfigSalesChannels);
        $salesChannel->setProductReviews($productReviews);
        $salesChannel->setSeoUrls($seoUrls);
        $salesChannel->setSeoUrlTemplates($seoUrlTemplates);
        $salesChannel->setMainCategories($mainCategories);
        $salesChannel->setProductExports($productExports);
        $salesChannel->setSalesChannelFiles($salesChannelFiles);
        $salesChannel->setHreflangDefaultDomain($hreflangDefaultDomain);
        $salesChannel->setAnalytics($analytics);
        $salesChannel->setCustomerGroupsRegistrations($customerGroupsRegistrations);
        $salesChannel->setBoundCustomers($boundCustomers);
        $salesChannel->setWishlists($wishlists);
        $salesChannel->setLandingPages($landingPages);
        $salesChannel->setMeasurementUnits($measurementUnits);

        static::assertSame($mailHeaderFooter, $salesChannel->getMailHeaderFooter());
        static::assertSame($currencies, $salesChannel->getCurrencies());
        static::assertSame($languages, $salesChannel->getLanguages());
        static::assertSame($currency, $salesChannel->getCurrency());
        static::assertSame($language, $salesChannel->getLanguage());
        static::assertSame($paymentMethod, $salesChannel->getPaymentMethod());
        static::assertSame($shippingMethod, $salesChannel->getShippingMethod());
        static::assertSame($country, $salesChannel->getCountry());
        static::assertSame($orders, $salesChannel->getOrders());
        static::assertSame($customers, $salesChannel->getCustomers());
        static::assertSame($type, $salesChannel->getType());
        static::assertSame($countries, $salesChannel->getCountries());
        static::assertSame($translations, $salesChannel->getTranslations());
        static::assertSame($paymentMethods, $salesChannel->getPaymentMethods());
        static::assertSame($shippingMethods, $salesChannel->getShippingMethods());
        static::assertSame($domains, $salesChannel->getDomains());
        static::assertSame($systemConfigs, $salesChannel->getSystemConfigs());
        static::assertSame($navigationCategory, $salesChannel->getNavigationCategory());
        static::assertSame($homeCmsPage, $salesChannel->getHomeCmsPage());
        static::assertSame($productVisibilities, $salesChannel->getProductVisibilities());
        static::assertSame($customerGroup, $salesChannel->getCustomerGroup());
        static::assertSame($newsletterRecipients, $salesChannel->getNewsletterRecipients());
        static::assertSame($promotionSalesChannels, $salesChannel->getPromotionSalesChannels());
        static::assertSame($numberRangeSalesChannels, $salesChannel->getNumberRangeSalesChannels());
        static::assertSame($footerCategory, $salesChannel->getFooterCategory());
        static::assertSame($serviceCategory, $salesChannel->getServiceCategory());
        static::assertSame($documentBaseConfigSalesChannels, $salesChannel->getDocumentBaseConfigSalesChannels());
        static::assertSame($productReviews, $salesChannel->getProductReviews());
        static::assertSame($seoUrls, $salesChannel->getSeoUrls());
        static::assertSame($seoUrlTemplates, $salesChannel->getSeoUrlTemplates());
        static::assertSame($mainCategories, $salesChannel->getMainCategories());
        static::assertSame($productExports, $salesChannel->getProductExports());
        static::assertSame($salesChannelFiles, $salesChannel->getSalesChannelFiles());
        static::assertSame($hreflangDefaultDomain, $salesChannel->getHreflangDefaultDomain());
        static::assertSame($analytics, $salesChannel->getAnalytics());
        static::assertSame($customerGroupsRegistrations, $salesChannel->getCustomerGroupsRegistrations());
        static::assertSame($boundCustomers, $salesChannel->getBoundCustomers());
        static::assertSame($wishlists, $salesChannel->getWishlists());
        static::assertSame($landingPages, $salesChannel->getLandingPages());
        static::assertSame($measurementUnits, $salesChannel->getMeasurementUnits());
    }
}
