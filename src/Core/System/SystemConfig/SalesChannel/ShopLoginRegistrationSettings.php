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

    public function getApiAlias(): string
    {
        return 'shop_settings_login_registration';
    }
}
