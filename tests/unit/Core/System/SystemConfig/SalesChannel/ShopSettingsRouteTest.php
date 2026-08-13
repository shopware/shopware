<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SalesChannel\ShopSettingsRoute;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopSettingsRoute::class)]
class ShopSettingsRouteTest extends TestCase
{
    public function testLoadReturnsSalesChannelResolvedSettings(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.basicInformation.shopName' => 'Demostore',
                'core.basicInformation.metaAuthor' => 'shopware AG',
                'core.basicInformation.metaRobots' => 'index,follow',
                'core.basicInformation.familyFriendly' => true,
                'core.basicInformation.firstNameFieldRequired' => true,
                'core.basicInformation.lastNameFieldRequired' => true,
                'core.basicInformation.phoneNumberFieldRequired' => true,
                'core.basicInformation.showRevocationButton' => true,

                'core.loginRegistration.passwordMinLength' => '12',
                'core.loginRegistration.createCustomerAccountDefault' => true,
                'core.loginRegistration.showSalutation' => true,
                'core.loginRegistration.showTitleField' => true,
                'core.loginRegistration.requireEmailConfirmation' => true,
                'core.loginRegistration.requirePasswordConfirmation' => true,
                'core.loginRegistration.doubleOptInRegistration' => true,
                'core.loginRegistration.doubleOptInGuestOrder' => true,
                'core.loginRegistration.showPhoneNumberField' => true,
                'core.loginRegistration.phoneNumberFieldRequired' => true,
                'core.loginRegistration.showBirthdayField' => true,
                'core.loginRegistration.birthdayFieldRequired' => true,
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.showAdditionalAddressField1' => true,
                'core.loginRegistration.additionalAddressField1Required' => true,
                'core.loginRegistration.showAdditionalAddressField2' => true,
                'core.loginRegistration.additionalAddressField2Required' => true,
                'core.loginRegistration.addressInputFieldArrangement' => 'zip-city-state',
                'core.loginRegistration.allowCustomerDeletion' => true,
                'core.loginRegistration.requireDataProtectionCheckbox' => true,

                'core.cart.maxQuantity' => '100',
                'core.cart.lineItemAddLimit' => '1000',
                'core.cart.showDeliveryTime' => true,
                'core.cart.showSubtotal' => true,
                'core.cart.columnTaxInsteadUnitPrice' => true,
                'core.cart.showCustomerComment' => true,
                'core.cart.showTosCheckbox' => true,
                'core.cart.openOffcanvasAfterAddToCart' => true,
                'core.cart.wishlistEnabled' => true,
                'core.cart.logoutGuestAfterCheckout' => true,
                'core.cart.enableOrderRefunds' => true,

                'core.listing.productsPerPage' => '24',
                'core.listing.allowBuyInListing' => true,
                'core.listing.showReview' => true,
                'core.listing.reviewsPerPage' => '10',
                'core.listing.disableEmptyFilterOptions' => true,
                'core.listing.markAsNew' => '30',
                'core.listing.hideCloseoutProductsWhenOutOfStock' => true,
                'core.listing.showVariantOptionInSearchSuggestionResult' => true,
                'core.listing.findBestVariant' => true,
                'core.listing.autoplayVideoInListing' => true,
                'core.listing.beforeListPriceSnippetKey' => 'listing.beforePrice',
                'core.listing.afterListPriceSnippetKey' => 'listing.afterPrice',

                'core.newsletter.doubleOptIn' => true,
                'core.newsletter.doubleOptInRegistered' => true,
            ],
        ]);

        $route = new ShopSettingsRoute($systemConfigService);

        $settings = $route->load(Generator::generateSalesChannelContext())->getSettings();

        static::assertSame('shop_settings', $settings->getApiAlias());

        $general = $settings->general;
        static::assertSame('Demostore', $general->shopName);
        static::assertSame('shopware AG', $general->metaAuthor);
        static::assertSame('index,follow', $general->metaRobots);
        static::assertTrue($general->familyFriendly);
        static::assertTrue($general->showRevocationButton);

        $contactForm = $settings->contactForm;
        static::assertTrue($contactForm->firstNameFieldRequired);
        static::assertTrue($contactForm->lastNameFieldRequired);
        static::assertTrue($contactForm->phoneNumberFieldRequired);

        $loginRegistration = $settings->loginRegistration;
        static::assertSame(12, $loginRegistration->passwordMinLength);
        static::assertTrue($loginRegistration->createCustomerAccountDefault);
        static::assertTrue($loginRegistration->showSalutation);
        static::assertTrue($loginRegistration->showTitleField);
        static::assertTrue($loginRegistration->requireEmailConfirmation);
        static::assertTrue($loginRegistration->requirePasswordConfirmation);
        static::assertTrue($loginRegistration->doubleOptInRegistration);
        static::assertTrue($loginRegistration->doubleOptInGuestOrder);
        static::assertTrue($loginRegistration->showPhoneNumberField);
        static::assertTrue($loginRegistration->phoneNumberFieldRequired);
        static::assertTrue($loginRegistration->showBirthdayField);
        static::assertTrue($loginRegistration->birthdayFieldRequired);
        static::assertTrue($loginRegistration->showAccountTypeSelection);
        static::assertTrue($loginRegistration->showAdditionalAddressField1);
        static::assertTrue($loginRegistration->additionalAddressField1Required);
        static::assertTrue($loginRegistration->showAdditionalAddressField2);
        static::assertTrue($loginRegistration->additionalAddressField2Required);
        static::assertSame('zip-city-state', $loginRegistration->addressInputFieldArrangement);
        static::assertTrue($loginRegistration->allowCustomerDeletion);
        static::assertTrue($loginRegistration->requireDataProtectionCheckbox);

        $cart = $settings->cart;
        static::assertSame(100, $cart->maxQuantity);
        static::assertSame(1000, $cart->lineItemAddLimit);
        static::assertTrue($cart->showDeliveryTime);
        static::assertTrue($cart->showSubtotal);
        static::assertTrue($cart->columnTaxInsteadUnitPrice);
        static::assertTrue($cart->showCustomerComment);
        static::assertTrue($cart->showTosCheckbox);
        static::assertTrue($cart->openOffcanvasAfterAddToCart);
        static::assertTrue($cart->wishlistEnabled);
        static::assertTrue($cart->logoutGuestAfterCheckout);
        static::assertTrue($cart->enableOrderRefunds);

        $listing = $settings->listing;
        static::assertSame(24, $listing->productsPerPage);
        static::assertTrue($listing->allowBuyInListing);
        static::assertTrue($listing->showReview);
        static::assertSame(10, $listing->reviewsPerPage);
        static::assertTrue($listing->disableEmptyFilterOptions);
        static::assertSame(30, $listing->markAsNew);
        static::assertTrue($listing->hideCloseoutProductsWhenOutOfStock);
        static::assertTrue($listing->showVariantOptionInSearchSuggestionResult);
        static::assertTrue($listing->findBestVariant);
        static::assertTrue($listing->autoplayVideoInListing);
        static::assertSame('listing.beforePrice', $listing->beforeListPriceSnippetKey);
        static::assertSame('listing.afterPrice', $listing->afterListPriceSnippetKey);

        $newsletter = $settings->newsletter;
        static::assertTrue($newsletter->doubleOptIn);
        static::assertTrue($newsletter->doubleOptInRegistered);
    }

    public function testLoadFallsBackToUnsetDefaultsWhenConfigIsEmpty(): void
    {
        $route = new ShopSettingsRoute(new StaticSystemConfigService());

        $settings = $route->load(Generator::generateSalesChannelContext())->getSettings();

        static::assertSame('', $settings->general->shopName);
        static::assertSame('', $settings->general->metaRobots);
        static::assertFalse($settings->general->familyFriendly);

        static::assertFalse($settings->contactForm->firstNameFieldRequired);
        static::assertFalse($settings->contactForm->lastNameFieldRequired);
        static::assertFalse($settings->contactForm->phoneNumberFieldRequired);

        static::assertSame(0, $settings->loginRegistration->passwordMinLength);
        static::assertFalse($settings->loginRegistration->showSalutation);
        static::assertSame('', $settings->loginRegistration->addressInputFieldArrangement);

        static::assertSame(0, $settings->cart->maxQuantity);
        static::assertFalse($settings->cart->wishlistEnabled);

        static::assertSame(0, $settings->listing->productsPerPage);
        static::assertFalse($settings->listing->showReview);
        static::assertSame('', $settings->listing->beforeListPriceSnippetKey);

        static::assertFalse($settings->newsletter->doubleOptIn);
        static::assertFalse($settings->newsletter->doubleOptInRegistered);
    }

    public function testLoadDoesNotLeakConfigOfOtherSalesChannels(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.passwordMinLength' => '10',
                'core.cart.wishlistEnabled' => false,
            ],
            'other-sales-channel-id' => [
                'core.loginRegistration.passwordMinLength' => '99',
                'core.cart.wishlistEnabled' => true,
            ],
        ]);

        $route = new ShopSettingsRoute($systemConfigService);

        $settings = $route->load(Generator::generateSalesChannelContext())->getSettings();

        static::assertSame(10, $settings->loginRegistration->passwordMinLength);
        static::assertFalse($settings->cart->wishlistEnabled);
    }

    public function testGetDecoratedThrows(): void
    {
        $route = new ShopSettingsRoute(new StaticSystemConfigService());

        static::expectExceptionObject(new DecorationPatternException(ShopSettingsRoute::class));

        $route->getDecorated();
    }
}
