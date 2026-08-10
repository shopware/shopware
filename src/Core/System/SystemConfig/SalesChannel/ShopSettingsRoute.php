<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ShopSettingsRoute extends AbstractShopSettingsRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getDecorated(): AbstractShopSettingsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/shop-settings',
        name: 'store-api.shop-settings',
        methods: [Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(SalesChannelContext $context): ShopSettingsRouteResponse
    {
        $salesChannelId = $context->getSalesChannelId();

        $settings = new ShopSettings(
            general: $this->loadGeneralSettings($salesChannelId),
            loginRegistration: $this->loadLoginRegistrationSettings($salesChannelId),
            cart: $this->loadCartSettings($salesChannelId),
            listing: $this->loadListingSettings($salesChannelId),
            newsletter: $this->loadNewsletterSettings($salesChannelId),
        );

        return new ShopSettingsRouteResponse($settings);
    }

    private function loadGeneralSettings(string $salesChannelId): ShopGeneralSettings
    {
        $domain = 'core.basicInformation.';

        return new ShopGeneralSettings(
            shopName: $this->systemConfigService->getString($domain . 'shopName', $salesChannelId),
            metaAuthor: $this->systemConfigService->getString($domain . 'metaAuthor', $salesChannelId),
            metaRobots: $this->systemConfigService->getString($domain . 'metaRobots', $salesChannelId),
            familyFriendly: $this->systemConfigService->getBool($domain . 'familyFriendly', $salesChannelId),
            firstNameFieldRequired: $this->systemConfigService->getBool($domain . 'firstNameFieldRequired', $salesChannelId),
            lastNameFieldRequired: $this->systemConfigService->getBool($domain . 'lastNameFieldRequired', $salesChannelId),
            phoneNumberFieldRequired: $this->systemConfigService->getBool($domain . 'phoneNumberFieldRequired', $salesChannelId),
            showRevocationButton: $this->systemConfigService->getBool($domain . 'showRevocationButton', $salesChannelId),
        );
    }

    private function loadLoginRegistrationSettings(string $salesChannelId): ShopLoginRegistrationSettings
    {
        $domain = 'core.loginRegistration.';

        return new ShopLoginRegistrationSettings(
            passwordMinLength: $this->systemConfigService->getInt($domain . 'passwordMinLength', $salesChannelId),
            createCustomerAccountDefault: $this->systemConfigService->getBool($domain . 'createCustomerAccountDefault', $salesChannelId),
            showSalutation: $this->systemConfigService->getBool($domain . 'showSalutation', $salesChannelId),
            showTitleField: $this->systemConfigService->getBool($domain . 'showTitleField', $salesChannelId),
            requireEmailConfirmation: $this->systemConfigService->getBool($domain . 'requireEmailConfirmation', $salesChannelId),
            requirePasswordConfirmation: $this->systemConfigService->getBool($domain . 'requirePasswordConfirmation', $salesChannelId),
            doubleOptInRegistration: $this->systemConfigService->getBool($domain . 'doubleOptInRegistration', $salesChannelId),
            doubleOptInGuestOrder: $this->systemConfigService->getBool($domain . 'doubleOptInGuestOrder', $salesChannelId),
            showPhoneNumberField: $this->systemConfigService->getBool($domain . 'showPhoneNumberField', $salesChannelId),
            phoneNumberFieldRequired: $this->systemConfigService->getBool($domain . 'phoneNumberFieldRequired', $salesChannelId),
            showBirthdayField: $this->systemConfigService->getBool($domain . 'showBirthdayField', $salesChannelId),
            birthdayFieldRequired: $this->systemConfigService->getBool($domain . 'birthdayFieldRequired', $salesChannelId),
            showAccountTypeSelection: $this->systemConfigService->getBool($domain . 'showAccountTypeSelection', $salesChannelId),
            showAdditionalAddressField1: $this->systemConfigService->getBool($domain . 'showAdditionalAddressField1', $salesChannelId),
            additionalAddressField1Required: $this->systemConfigService->getBool($domain . 'additionalAddressField1Required', $salesChannelId),
            showAdditionalAddressField2: $this->systemConfigService->getBool($domain . 'showAdditionalAddressField2', $salesChannelId),
            additionalAddressField2Required: $this->systemConfigService->getBool($domain . 'additionalAddressField2Required', $salesChannelId),
            addressInputFieldArrangement: $this->systemConfigService->getString($domain . 'addressInputFieldArrangement', $salesChannelId),
            allowCustomerDeletion: $this->systemConfigService->getBool($domain . 'allowCustomerDeletion', $salesChannelId),
            requireDataProtectionCheckbox: $this->systemConfigService->getBool($domain . 'requireDataProtectionCheckbox', $salesChannelId),
        );
    }

    private function loadCartSettings(string $salesChannelId): ShopCartSettings
    {
        $domain = 'core.cart.';

        return new ShopCartSettings(
            maxQuantity: $this->systemConfigService->getInt($domain . 'maxQuantity', $salesChannelId),
            lineItemAddLimit: $this->systemConfigService->getInt($domain . 'lineItemAddLimit', $salesChannelId),
            showDeliveryTime: $this->systemConfigService->getBool($domain . 'showDeliveryTime', $salesChannelId),
            showSubtotal: $this->systemConfigService->getBool($domain . 'showSubtotal', $salesChannelId),
            columnTaxInsteadUnitPrice: $this->systemConfigService->getBool($domain . 'columnTaxInsteadUnitPrice', $salesChannelId),
            showCustomerComment: $this->systemConfigService->getBool($domain . 'showCustomerComment', $salesChannelId),
            showTosCheckbox: $this->systemConfigService->getBool($domain . 'showTosCheckbox', $salesChannelId),
            openOffcanvasAfterAddToCart: $this->systemConfigService->getBool($domain . 'openOffcanvasAfterAddToCart', $salesChannelId),
            wishlistEnabled: $this->systemConfigService->getBool($domain . 'wishlistEnabled', $salesChannelId),
            logoutGuestAfterCheckout: $this->systemConfigService->getBool($domain . 'logoutGuestAfterCheckout', $salesChannelId),
            enableOrderRefunds: $this->systemConfigService->getBool($domain . 'enableOrderRefunds', $salesChannelId),
        );
    }

    private function loadListingSettings(string $salesChannelId): ShopListingSettings
    {
        $domain = 'core.listing.';

        return new ShopListingSettings(
            productsPerPage: $this->systemConfigService->getInt($domain . 'productsPerPage', $salesChannelId),
            allowBuyInListing: $this->systemConfigService->getBool($domain . 'allowBuyInListing', $salesChannelId),
            showReview: $this->systemConfigService->getBool($domain . 'showReview', $salesChannelId),
            reviewsPerPage: $this->systemConfigService->getInt($domain . 'reviewsPerPage', $salesChannelId),
            disableEmptyFilterOptions: $this->systemConfigService->getBool($domain . 'disableEmptyFilterOptions', $salesChannelId),
            markAsNew: $this->systemConfigService->getInt($domain . 'markAsNew', $salesChannelId),
            hideCloseoutProductsWhenOutOfStock: $this->systemConfigService->getBool($domain . 'hideCloseoutProductsWhenOutOfStock', $salesChannelId),
            showVariantOptionInSearchSuggestionResult: $this->systemConfigService->getBool($domain . 'showVariantOptionInSearchSuggestionResult', $salesChannelId),
            findBestVariant: $this->systemConfigService->getBool($domain . 'findBestVariant', $salesChannelId),
            autoplayVideoInListing: $this->systemConfigService->getBool($domain . 'autoplayVideoInListing', $salesChannelId),
            beforeListPriceSnippetKey: $this->systemConfigService->getString($domain . 'beforeListPriceSnippetKey', $salesChannelId),
            afterListPriceSnippetKey: $this->systemConfigService->getString($domain . 'afterListPriceSnippetKey', $salesChannelId),
        );
    }

    private function loadNewsletterSettings(string $salesChannelId): ShopNewsletterSettings
    {
        $domain = 'core.newsletter.';

        return new ShopNewsletterSettings(
            doubleOptIn: $this->systemConfigService->getBool($domain . 'doubleOptIn', $salesChannelId),
            doubleOptInRegistered: $this->systemConfigService->getBool($domain . 'doubleOptInRegistered', $salesChannelId),
        );
    }
}
