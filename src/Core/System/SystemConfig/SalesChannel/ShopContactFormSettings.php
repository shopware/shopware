<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Form field settings (core.basicInformation), used by the contact form
 * and the online revocation request form.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ShopContactFormSettings extends Struct
{
    use ConfigCastTrait;

    /**
     * @internal
     */
    public function __construct(
        public readonly bool $firstNameFieldRequired,
        public readonly bool $lastNameFieldRequired,
        public readonly bool $phoneNumberFieldRequired,
    ) {
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $config The values of the core.basicInformation config domain
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            firstNameFieldRequired: self::boolValue($config, 'firstNameFieldRequired'),
            lastNameFieldRequired: self::boolValue($config, 'lastNameFieldRequired'),
            phoneNumberFieldRequired: self::boolValue($config, 'phoneNumberFieldRequired'),
        );
    }

    public function getApiAlias(): string
    {
        return 'shop_settings_contact_form';
    }
}
