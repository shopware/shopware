<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopIdChangeResolver;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Storefront\Theme\ThemeAppLifecycleHandler;

/**
 * @internal
 *
 * Resolver used when apps should be uninstalled
 * and the shopId should be regenerated, meaning the old shops and old apps work like before
 * apps in the current installation will be uninstalled without informing them about that (as they still run on the old installation)
 */
#[Package('framework')]
class UninstallAppsStrategy extends AbstractShopIdChangeStrategy
{
    final public const STRATEGY_NAME = 'uninstall-apps';

    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly ShopIdProvider $shopIdProvider,
        /** @phpstan-ignore phpat.restrictNamespacesInCore (Storefront dependency is nullable. Don't do that! Will be fixed with https://github.com/shopware/shopware/issues/12966) */
        private readonly ?ThemeAppLifecycleHandler $themeLifecycleHandler
    ) {
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
        return 'This is typically the right option if you have made a copy of your shop (e.g. a staging or testing environment of a production shop) and you don’t want to use the apps in this copy. Shopware will delete the apps without notifying the app servers. A new shop identifier will be generated and your shop will identify as a new shop.';
    }

    public function resolve(Context $context): void
    {
        $this->shopIdProvider->deleteShopId();

        foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
            // Delete app manually, to not inform the app backend about the deactivation
            // as the app is still running in the old shop with the same shopId
            if ($this->themeLifecycleHandler) {
                $this->themeLifecycleHandler->handleUninstall(new AppDeactivatedEvent($app, $context));
            }
            $this->appRepository->delete([['id' => $app->getId()]], $context);
        }
    }
}
