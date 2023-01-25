<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\AppUrlChangeResolver;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 *
 * Resolver used when apps should be reinstalled
 * and the shopId should be regenerated, meaning the old shops and old apps work like before
 * apps in the current installation may lose historical data
 *
 * Will run through the registration process for all apps again
 * with the new appUrl and new shopId and throw installed events for every app
 */
#[Package('core')]
class ReinstallAppsStrategy extends AbstractAppUrlChangeStrategy
{
    final public const STRATEGY_NAME = 'reinstall-apps';

    public function __construct(
        AbstractAppLoader $appLoader,
        EntityRepository $appRepository,
        AppRegistrationService $registrationService,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher
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
        return 'Reinstall all apps anew for the new URL, so app communication on the old URLs installation keeps
        working like before. App-data from the old installation will not be available in this installation.';
    }

    public function resolve(Context $context): void
    {
        $this->systemConfigService->delete(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY);

        $this->forEachInstalledApp($context, function (Manifest $manifest, AppEntity $app, Context $context): void {
            $this->reRegisterApp($manifest, $app, $context);
            $this->eventDispatcher->dispatch(
                new AppInstalledEvent($app, $manifest, $context)
            );
        });
    }
}
