<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class LoginRegistrationSettingsRoute extends AbstractLoginRegistrationSettingsRoute
{
    private const CONFIG_DOMAIN = 'core.loginRegistration.';

    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getDecorated(): AbstractLoginRegistrationSettingsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/login-registration-settings',
        name: 'store-api.login-registration-settings',
        methods: [Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(SalesChannelContext $context): LoginRegistrationSettingsRouteResponse
    {
        $salesChannelId = $context->getSalesChannelId();

        $settings = new LoginRegistrationSettings(
            passwordMinLength: $this->getInt('passwordMinLength', $salesChannelId),
            createCustomerAccountDefault: $this->getBool('createCustomerAccountDefault', $salesChannelId),
            showSalutation: $this->getBool('showSalutation', $salesChannelId),
            showTitleField: $this->getBool('showTitleField', $salesChannelId),
            requireEmailConfirmation: $this->getBool('requireEmailConfirmation', $salesChannelId),
            requirePasswordConfirmation: $this->getBool('requirePasswordConfirmation', $salesChannelId),
            doubleOptInRegistration: $this->getBool('doubleOptInRegistration', $salesChannelId),
            doubleOptInGuestOrder: $this->getBool('doubleOptInGuestOrder', $salesChannelId),
            showPhoneNumberField: $this->getBool('showPhoneNumberField', $salesChannelId),
            phoneNumberFieldRequired: $this->getBool('phoneNumberFieldRequired', $salesChannelId),
            showBirthdayField: $this->getBool('showBirthdayField', $salesChannelId),
            birthdayFieldRequired: $this->getBool('birthdayFieldRequired', $salesChannelId),
            showAccountTypeSelection: $this->getBool('showAccountTypeSelection', $salesChannelId),
            showAdditionalAddressField1: $this->getBool('showAdditionalAddressField1', $salesChannelId),
            additionalAddressField1Required: $this->getBool('additionalAddressField1Required', $salesChannelId),
            showAdditionalAddressField2: $this->getBool('showAdditionalAddressField2', $salesChannelId),
            additionalAddressField2Required: $this->getBool('additionalAddressField2Required', $salesChannelId),
            addressInputFieldArrangement: $this->getString('addressInputFieldArrangement', $salesChannelId),
            allowCustomerDeletion: $this->getBool('allowCustomerDeletion', $salesChannelId),
            requireDataProtectionCheckbox: $this->getBool('requireDataProtectionCheckbox', $salesChannelId),
        );

        return new LoginRegistrationSettingsRouteResponse($settings);
    }

    private function getBool(string $key, string $salesChannelId): bool
    {
        return $this->systemConfigService->getBool(self::CONFIG_DOMAIN . $key, $salesChannelId);
    }

    private function getInt(string $key, string $salesChannelId): int
    {
        return $this->systemConfigService->getInt(self::CONFIG_DOMAIN . $key, $salesChannelId);
    }

    private function getString(string $key, string $salesChannelId): string
    {
        return $this->systemConfigService->getString(self::CONFIG_DOMAIN . $key, $salesChannelId);
    }
}
