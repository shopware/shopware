<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopIdChangeResolver;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Resolver used when apps should be re-registered with a new shopId,
 * meaning the old shop and its apps continue to work like before,
 * while this installation registers as a brand-new shop at the app servers
 *
 * Will run through the registration process for all apps again
 * with the new appUrl and new shopId and replay the install lifecycle events for every app
 */
#[Package('framework')]
class ReinstallAppsStrategy implements ShopIdChangeStrategy
{
    final public const STRATEGY_NAME = 'reinstall-apps';

    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly ManifestFactory $manifestFactory,
        private readonly EntityRepository $appRepository,
        private readonly AppManager $appManager,
        private readonly ShopIdProvider $shopIdProvider
    ) {
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

        // Re-registering contacts external app servers. If one app is unreachable we still want the
        // remaining apps to learn about the shop change, then report all failed apps together.
        /** @var array<string, \Throwable> $failedApps */
        $failedApps = [];

        foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
            $manifest = $this->manifestFactory->createFromApp($app);

            if (!$manifest->getSetup()) {
                continue;
            }

            try {
                $this->appManager->reregister($app, $manifest, $context);
            } catch (\Throwable $e) {
                $failedApps[$app->getName()] = $e;
            }
        }

        if ($failedApps !== []) {
            throw AppException::reRegistrationFailed(array_keys($failedApps), reset($failedApps));
        }
    }
}
