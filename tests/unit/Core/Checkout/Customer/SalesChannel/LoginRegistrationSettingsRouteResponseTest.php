<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRegistrationSettings;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRegistrationSettingsRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoginRegistrationSettingsRouteResponse::class)]
class LoginRegistrationSettingsRouteResponseTest extends TestCase
{
    public function testGetSettingsReturnsWrappedStruct(): void
    {
        $settings = new LoginRegistrationSettings(
            passwordMinLength: 8,
            createCustomerAccountDefault: false,
            showSalutation: true,
            showTitleField: false,
            requireEmailConfirmation: false,
            requirePasswordConfirmation: true,
            doubleOptInRegistration: false,
            doubleOptInGuestOrder: false,
            showPhoneNumberField: false,
            phoneNumberFieldRequired: false,
            showBirthdayField: true,
            birthdayFieldRequired: false,
            showAccountTypeSelection: true,
            showAdditionalAddressField1: false,
            additionalAddressField1Required: false,
            showAdditionalAddressField2: false,
            additionalAddressField2Required: false,
            addressInputFieldArrangement: 'zip-city-state',
            allowCustomerDeletion: false,
            requireDataProtectionCheckbox: true,
        );

        $response = new LoginRegistrationSettingsRouteResponse($settings);

        static::assertSame($settings, $response->getSettings());
    }
}
