<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\AppUrlChangeResolver;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 *
 * Resolver used when shop is moved from one URL to another
 * and the shopId (and the data in the app backends associated with it) should be kept
 *
 * Will run through the registration process for all apps again
 * with the new appUrl so the apps can save the new URL and generate new Secrets
 * that way communication from the old shop to the app backend will be blocked in the future
 */
#[Package('core')]
class MoveShopPermanentlyStrategy extends AbstractAppUrlChangeStrategy
{
    final public const STRATEGY_NAME = 'move-shop-permanently';

    public function __construct(
        AbstractAppLoader $appLoader,
        EntityRepository $appRepository,
        AppRegistrationService $registrationService,
        private readonly SystemConfigService $systemConfigService
    ) {
        parent::__construct($appLoader, $appRepository, $registrationService);
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
            'app_url' => EnvironmentHelper::getVariable('APP_URL'),
            'value' => $shopId,
        ]);

        $this->forEachInstalledApp($context, function (Manifest $manifest, AppEntity $app, Context $context): void {
            $this->reRegisterApp($manifest, $app, $context);
        });
    }
}
