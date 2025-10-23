<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopIdChangeResolver;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * Resolver used when apps should be reinstalled
 * and the shopId should be regenerated, meaning the old shops and old apps work like before
 * apps in the current installation may lose historical data
 *
 * Will run through the registration process for all apps again
 * with the new appUrl and new shopId and throw installed events for every app
 */
#[Package('framework')]
class ReinstallAppsStrategy extends AbstractShopIdChangeStrategy
{
    final public const STRATEGY_NAME = 'reinstall-apps';

    public function __construct(
        SourceResolver $sourceResolver,
        EntityRepository $appRepository,
        AppRegistrationService $registrationService,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($sourceResolver, $appRepository, $registrationService);
    }

    public function getDecorated(): AbstractShopIdChangeStrategy
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return self::STRATEGY_NAME;
    }

    public function getDescription(): string
    {
        return 'This is typically the right option if you have made a copy of your shop (e.g. a staging or testing environment of a production shop) and you want to use the apps in this copy. Shopware will re-install the apps and newly register at the app servers using the new shop identifier. Your shop will identify as a new shop.';
    }

    public function resolve(Context $context): void
    {
        $this->shopIdProvider->deleteShopId();

        $this->forEachInstalledApp($context, function (Manifest $manifest, AppEntity $app, Context $context): void {
            $this->reRegisterApp($manifest, $app, $context);
            $this->eventDispatcher->dispatch(
                new AppInstalledEvent($app, $manifest, $context)
            );
        });
    }
}
