<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Checkers;

use Shopware\Core\Framework\Update\Struct\ValidationResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class LicenseCheck implements CheckerInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function supports(string $check): bool
    {
        return $check === 'licensecheck';
    }

    /**
     * @param int|string|array $values
     */
    public function check($values): ValidationResult
    {
        $licenseHost = $this->systemConfigService->get('core.store.licenseHost');

        if (empty($licenseHost)) {
            return new ValidationResult('noShopwareLicense', self::VALIDATION_SUCCESS, 'noShopwareLicense', []);
        }

        return new ValidationResult('noShopwareLicense', self::VALIDATION_SUCCESS, 'noShopwareLicense', []);
    }
}
