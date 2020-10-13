<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\AppUrlChangeResolver;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Resolver used when shop is moved from one URL to another
 * and the shopId (and the data in the app backends associated with it) should be kept
 *
 * Will run through the registration process for all apps again
 * with the new appUrl so the apps can save the new URL and generate new Secrets
 * that way communication from the old shop to the app backend will be blocked in the future
 */
class MoveShopPermanentlyStrategy extends AbstractAppUrlChangeStrategy
{
    public const STRATEGY_NAME = 'move-shop-permanently';

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        AbstractAppLoader $appLoader,
        EntityRepositoryInterface $appRepository,
        AppRegistrationService $registrationService,
        SystemConfigService $systemConfigService
    ) {
        parent::__construct($appLoader, $appRepository, $registrationService);

        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractAppUrlChangeStrategy
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return self::STRATEGY_NAME;
    }

    public function getDescription(): string
    {
        return 'Use this URL for communicating with installed apps, this will disable communication to apps on the old
        URLs installation, but the app-data from the old installation will be available in this installation.';
    }

    public function resolve(Context $context): void
    {
        $shopIdConfig = (array) $this->systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY);
        $shopId = $shopIdConfig['value'];

        $this->systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => $_SERVER['APP_URL'],
            'value' => $shopId,
        ]);

        $this->forEachInstalledApp($context, function (Manifest $manifest, AppEntity $app, Context $context): void {
            $this->reRegisterApp($manifest, $app, $context);
        });

        $this->systemConfigService->delete(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY);
    }
}
