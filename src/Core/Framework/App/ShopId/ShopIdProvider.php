<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class ShopIdProvider
{
    public const SHOP_ID_SYSTEM_CONFIG_KEY = 'core.app.shopId';
    public const SHOP_DOMAIN_CHANGE_CONFIG_KEY = 'core.app.domainChange';

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @throws AppUrlChangeDetectedException
     */
    public function getShopId(): string
    {
        $shopId = $this->systemConfigService->get(self::SHOP_ID_SYSTEM_CONFIG_KEY);

        if (!\is_array($shopId)) {
            $newShopId = $this->generateShopId();
            $this->systemConfigService->set(self::SHOP_ID_SYSTEM_CONFIG_KEY, [
                'app_url' => EnvironmentHelper::getVariable('APP_URL'),
                'value' => $newShopId,
            ]);

            return $newShopId;
        }

        if (EnvironmentHelper::getVariable('APP_URL') !== ($shopId['app_url'] ?? '')) {
            $this->systemConfigService->set(self::SHOP_DOMAIN_CHANGE_CONFIG_KEY, true);
            /** @var string $appUrl */
            $appUrl = EnvironmentHelper::getVariable('APP_URL');

            throw new AppUrlChangeDetectedException($shopId['app_url'], $appUrl);
        }
        $this->systemConfigService->delete(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY);

        return $shopId['value'];
    }

    private function generateShopId(): string
    {
        return Random::getAlphanumericString(16);
    }
}
