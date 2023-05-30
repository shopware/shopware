<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\AppUrlChangeResolver;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\ThemeAppLifecycleHandler;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 *
 * Resolver used when apps should be uninstalled
 * and the shopId should be regenerated, meaning the old shops and old apps work like before
 * apps in the current installation will be uninstalled without informing them about that (as they still run on the old installation)
 */
#[Package('core')]
class UninstallAppsStrategy extends AbstractAppUrlChangeStrategy
{
    final public const STRATEGY_NAME = 'uninstall-apps';

    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly ?ThemeAppLifecycleHandler $themeLifecycleHandler
    ) {
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
        return 'Uninstall all apps on this URL, so app communication on the old URLs installation keeps
        working like before.';
    }

    public function resolve(Context $context): void
    {
        $this->systemConfigService->delete(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $context)->getEntities();

        foreach ($apps as $app) {
            // Delete app manually, to not inform the app backend about the deactivation
            // as the app is still running in the old shop with the same shopId
            if ($this->themeLifecycleHandler) {
                $this->themeLifecycleHandler->handleUninstall(new AppDeactivatedEvent($app, $context));
            }
            $this->appRepository->delete([['id' => $app->getId()]], $context);
        }
    }
}
