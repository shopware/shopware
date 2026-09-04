<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Log\Package;
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
        return $systemConfigService->getBool(self::CONFIG_SHOW, $salesChannelId)
            && $systemConfigService->getBool(self::CONFIG_REQUIRED, $salesChannelId);
    }

    public static function areVisible(SystemConfigService $systemConfigService, ?string $salesChannelId): bool
    {
        return $systemConfigService->getBool(self::CONFIG_SHOW, $salesChannelId);
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
