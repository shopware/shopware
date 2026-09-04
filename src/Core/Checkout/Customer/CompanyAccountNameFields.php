<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Resolves whether a commercial customer still has to provide a contact person.
 *
 * Read from every validation entry point that touches a customer or address name, so that
 * relaxing one of them cannot leave another rejecting the empty value.
 */
#[Package('checkout')]
final class CompanyAccountNameFields
{
    public const CONFIG_SHOW = 'core.loginRegistration.showNameFieldsForCompanyAccounts';
    public const CONFIG_REQUIRED = 'core.loginRegistration.nameFieldsRequiredForCompanyAccounts';

    private function __construct()
    {
    }

    public static function areRequired(SystemConfigService $systemConfigService, ?string $salesChannelId): bool
    {
        // a hidden field is never submitted, so it cannot be required
        return self::isEnabled($systemConfigService, self::CONFIG_SHOW, $salesChannelId)
            && self::isEnabled($systemConfigService, self::CONFIG_REQUIRED, $salesChannelId);
    }

    public static function areVisible(SystemConfigService $systemConfigService, ?string $salesChannelId): bool
    {
        return self::isEnabled($systemConfigService, self::CONFIG_SHOW, $salesChannelId);
    }

    /**
     * Both settings default to on. An absent key would otherwise read as `false` and silently make
     * the contact person optional on an installation that never opted in.
     */
    private static function isEnabled(SystemConfigService $systemConfigService, string $key, ?string $salesChannelId): bool
    {
        $value = $systemConfigService->get($key, $salesChannelId);

        return $value === null ? true : (bool) $value;
    }

    /**
     * The name fields are `Required` with `AllowEmptyString`, so the data abstraction layer accepts
     * an empty string but still rejects null. A hidden or skipped input is absent from the request,
     * so it is normalised before the payload is built.
     */
    public static function normalize(DataBag $data): void
    {
        foreach (['firstName', 'lastName'] as $property) {
            if ($data->get($property) === null) {
                $data->set($property, '');
            }
        }
    }

    /**
     * Drops the blank check from the person name while keeping its length limit. Only properties the
     * definition already carries are touched, so relaxing never introduces a constraint of its own.
     */
    public static function relax(DataValidationDefinition $validation, Length $firstName, Length $lastName): void
    {
        foreach (['firstName' => $firstName, 'lastName' => $lastName] as $property => $length) {
            if ($validation->getProperty($property) !== []) {
                $validation->set($property, $length);
            }
        }
    }
}
