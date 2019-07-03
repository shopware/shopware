<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Checkers;

use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Update\Struct\ValidationResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class LicenseCheck implements CheckerInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var StoreClient
     */
    private $storeClient;

    public function __construct(SystemConfigService $systemConfigService, StoreClient $storeClient)
    {
        $this->systemConfigService = $systemConfigService;
        $this->storeClient = $storeClient;
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

        if (empty($licenseHost) || $this->storeClient->isShopUpgradeable()) {
            return new ValidationResult('validShopwareLicense', self::VALIDATION_SUCCESS, 'validShopwareLicense', []);
        }

        return new ValidationResult('invalidShopwareLicense', self::VALIDATION_ERROR, 'invalidShopwareLicense', []);
    }
}
