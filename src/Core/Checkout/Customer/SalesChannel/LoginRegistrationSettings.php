<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Sales-channel-resolved subset of the `core.loginRegistration.*` system config
 * that is relevant for rendering and validating login/registration forms.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class LoginRegistrationSettings extends Struct
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
        return 'login_registration_settings';
    }
}
