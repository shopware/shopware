<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

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
}
