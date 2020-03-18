<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Client;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class GoogleShoppingClientFactory
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function createClient(): GoogleShoppingClient
    {
        return new GoogleShoppingClient(
            $this->systemConfigService->get('core.googleShopping.clientId'),
            $this->systemConfigService->get('core.googleShopping.clientSecret'),
            $this->systemConfigService->get('core.googleShopping.redirectUri')
        );
    }
}
