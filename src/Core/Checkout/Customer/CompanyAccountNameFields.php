<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Length;

/**
 * @internal
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
        return self::isEnabled($systemConfigService, self::CONFIG_SHOW, $salesChannelId)
            && self::isEnabled($systemConfigService, self::CONFIG_REQUIRED, $salesChannelId);
    }

    public static function areVisible(SystemConfigService $systemConfigService, ?string $salesChannelId): bool
    {
        return self::isEnabled($systemConfigService, self::CONFIG_SHOW, $salesChannelId);
    }

    public static function normalize(DataBag $data): void
    {
        foreach (['firstName', 'lastName'] as $property) {
            if ($data->get($property) === null) {
                $data->set($property, '');
            }
        }
    }

    public static function relax(DataValidationDefinition $validation, Length $firstName, Length $lastName): void
    {
        foreach (['firstName' => $firstName, 'lastName' => $lastName] as $property => $length) {
            if ($validation->getProperty($property) !== []) {
                $validation->set($property, $length);
            }
        }
    }

    private static function isEnabled(SystemConfigService $systemConfigService, string $key, ?string $salesChannelId): bool
    {
        $value = $systemConfigService->get($key, $salesChannelId);

        return $value === null ? true : (bool) $value;
    }
}
