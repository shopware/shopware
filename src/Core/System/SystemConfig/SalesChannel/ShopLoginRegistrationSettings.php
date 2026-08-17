<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Login, registration and address form settings (core.loginRegistration).
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ShopLoginRegistrationSettings extends Struct
{
    use ConfigCastTrait;

    /**
     * @internal
     */
    public function __construct(
        public readonly int $passwordMinLength,
        public readonly bool $createCustomerAccountDefault,
        public readonly bool $showSalutation,
        public readonly bool $showTitleField,
        public readonly bool $requireEmailConfirmation,
        public readonly bool $requirePasswordConfirmation,
        public readonly bool $doubleOptInRegistration,
        public readonly bool $doubleOptInGuestOrder,
        public readonly bool $showPhoneNumberField,
        public readonly bool $phoneNumberFieldRequired,
        public readonly bool $showBirthdayField,
        public readonly bool $birthdayFieldRequired,
        public readonly bool $showAccountTypeSelection,
        public readonly bool $showAdditionalAddressField1,
        public readonly bool $additionalAddressField1Required,
        public readonly bool $showAdditionalAddressField2,
        public readonly bool $additionalAddressField2Required,
        public readonly string $addressInputFieldArrangement,
        public readonly bool $allowCustomerDeletion,
        public readonly bool $requireDataProtectionCheckbox,
    ) {
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $config The values of the core.loginRegistration config domain
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            passwordMinLength: self::intValue($config, 'passwordMinLength'),
            createCustomerAccountDefault: self::boolValue($config, 'createCustomerAccountDefault'),
            showSalutation: self::boolValue($config, 'showSalutation'),
            showTitleField: self::boolValue($config, 'showTitleField'),
            requireEmailConfirmation: self::boolValue($config, 'requireEmailConfirmation'),
            requirePasswordConfirmation: self::boolValue($config, 'requirePasswordConfirmation'),
            doubleOptInRegistration: self::boolValue($config, 'doubleOptInRegistration'),
            doubleOptInGuestOrder: self::boolValue($config, 'doubleOptInGuestOrder'),
            showPhoneNumberField: self::boolValue($config, 'showPhoneNumberField'),
            phoneNumberFieldRequired: self::boolValue($config, 'phoneNumberFieldRequired'),
            showBirthdayField: self::boolValue($config, 'showBirthdayField'),
            birthdayFieldRequired: self::boolValue($config, 'birthdayFieldRequired'),
            showAccountTypeSelection: self::boolValue($config, 'showAccountTypeSelection'),
            showAdditionalAddressField1: self::boolValue($config, 'showAdditionalAddressField1'),
            additionalAddressField1Required: self::boolValue($config, 'additionalAddressField1Required'),
            showAdditionalAddressField2: self::boolValue($config, 'showAdditionalAddressField2'),
            additionalAddressField2Required: self::boolValue($config, 'additionalAddressField2Required'),
            addressInputFieldArrangement: self::stringValue($config, 'addressInputFieldArrangement'),
            allowCustomerDeletion: self::boolValue($config, 'allowCustomerDeletion'),
            requireDataProtectionCheckbox: self::boolValue($config, 'requireDataProtectionCheckbox'),
        );
    }

    public function getApiAlias(): string
    {
        return 'shop_settings_login_registration';
    }
}
