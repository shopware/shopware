<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRegistrationSettingsRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoginRegistrationSettingsRoute::class)]
class LoginRegistrationSettingsRouteTest extends TestCase
{
    public function testLoadReturnsSalesChannelResolvedSettings(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
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
            ],
        ]);

        $route = new LoginRegistrationSettingsRoute($systemConfigService);

        $settings = $route->load(Generator::generateSalesChannelContext())->getSettings();

        static::assertSame(12, $settings->passwordMinLength);
        static::assertTrue($settings->createCustomerAccountDefault);
        static::assertTrue($settings->showSalutation);
        static::assertTrue($settings->showTitleField);
        static::assertTrue($settings->requireEmailConfirmation);
        static::assertTrue($settings->requirePasswordConfirmation);
        static::assertTrue($settings->doubleOptInRegistration);
        static::assertTrue($settings->doubleOptInGuestOrder);
        static::assertTrue($settings->showPhoneNumberField);
        static::assertTrue($settings->phoneNumberFieldRequired);
        static::assertTrue($settings->showBirthdayField);
        static::assertTrue($settings->birthdayFieldRequired);
        static::assertTrue($settings->showAccountTypeSelection);
        static::assertTrue($settings->showAdditionalAddressField1);
        static::assertTrue($settings->additionalAddressField1Required);
        static::assertTrue($settings->showAdditionalAddressField2);
        static::assertTrue($settings->additionalAddressField2Required);
        static::assertSame('zip-city-state', $settings->addressInputFieldArrangement);
        static::assertTrue($settings->allowCustomerDeletion);
        static::assertTrue($settings->requireDataProtectionCheckbox);
        static::assertSame('login_registration_settings', $settings->getApiAlias());
    }

    public function testLoadFallsBackToUnsetDefaultsWhenConfigIsEmpty(): void
    {
        $route = new LoginRegistrationSettingsRoute(new StaticSystemConfigService());

        $settings = $route->load(Generator::generateSalesChannelContext())->getSettings();

        static::assertSame(0, $settings->passwordMinLength);
        static::assertFalse($settings->createCustomerAccountDefault);
        static::assertFalse($settings->showSalutation);
        static::assertFalse($settings->showTitleField);
        static::assertFalse($settings->requireEmailConfirmation);
        static::assertFalse($settings->requirePasswordConfirmation);
        static::assertFalse($settings->doubleOptInRegistration);
        static::assertFalse($settings->doubleOptInGuestOrder);
        static::assertFalse($settings->showPhoneNumberField);
        static::assertFalse($settings->phoneNumberFieldRequired);
        static::assertFalse($settings->showBirthdayField);
        static::assertFalse($settings->birthdayFieldRequired);
        static::assertFalse($settings->showAccountTypeSelection);
        static::assertFalse($settings->showAdditionalAddressField1);
        static::assertFalse($settings->additionalAddressField1Required);
        static::assertFalse($settings->showAdditionalAddressField2);
        static::assertFalse($settings->additionalAddressField2Required);
        static::assertSame('', $settings->addressInputFieldArrangement);
        static::assertFalse($settings->allowCustomerDeletion);
        static::assertFalse($settings->requireDataProtectionCheckbox);
    }

    public function testLoadDoesNotFallBackToGlobalConfigOfOtherSalesChannels(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.loginRegistration.passwordMinLength' => '10',
                'core.loginRegistration.showSalutation' => false,
            ],
            'other-sales-channel-id' => [
                'core.loginRegistration.passwordMinLength' => '99',
                'core.loginRegistration.showSalutation' => true,
            ],
        ]);

        $route = new LoginRegistrationSettingsRoute($systemConfigService);

        $settings = $route->load(Generator::generateSalesChannelContext())->getSettings();

        static::assertSame(10, $settings->passwordMinLength);
        static::assertFalse($settings->showSalutation);
    }

    public function testGetDecoratedThrows(): void
    {
        $route = new LoginRegistrationSettingsRoute(new StaticSystemConfigService());

        static::expectExceptionObject(new DecorationPatternException(LoginRegistrationSettingsRoute::class));

        $route->getDecorated();
    }
}
