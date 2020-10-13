<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\AppUrlChangeResolver;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Resolver used when apps should be reinstalled
 * and the shopId should be regenerated, meaning the old shops and old apps work like before
 * apps in the current installation may lose historical data
 *
 * Will run through the registration process for all apps again
 * with the new appUrl and new shopId and throw installed events for every app
 */
class ReinstallAppsStrategy extends AbstractAppUrlChangeStrategy
{
    public const STRATEGY_NAME = 'reinstall-apps';

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        AbstractAppLoader $appLoader,
        EntityRepositoryInterface $appRepository,
        AppRegistrationService $registrationService,
        SystemConfigService $systemConfigService,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($appLoader, $appRepository, $registrationService);

        $this->systemConfigService = $systemConfigService;
        $this->eventDispatcher = $eventDispatcher;
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

        $this->systemConfigService->delete(ShopIdProvider::SHOP_DOMAIN_CHANGE_CONFIG_KEY);
    }
}
