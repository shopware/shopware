<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopIdChangeResolver;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
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
 * Resolver used when shop is moved from one URL to another
 * and the shopId (and the data in the app backends associated with it) should be kept
 *
 * Will run through the registration process for all apps again
 * with the new appUrl so the apps can save the new URL and generate new Secrets
 * that way communication from the old shop to the app backend will be blocked in the future
 */
#[Package('framework')]
class MoveShopPermanentlyStrategy implements ShopIdChangeStrategy
{
    final public const STRATEGY_NAME = 'move-shop-permanently';

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
        return 'This is typically the right option if you have permanently moved your shop to a different infrastructure or new environment. Shopware will notify apps (i.e. re-register at the app servers) using the same shop identifier and apps remain installed. Your shop will identify as the same shop as before. This means, that this instance will override the app data of the original installation.';
    }

    public function resolve(Context $context): void
    {
        try {
            $this->shopIdProvider->reset();
            $this->shopIdProvider->getShopId();

            // no resolution needed
            return;
        } catch (ShopIdChangeSuggestedException $e) {
            $this->shopIdProvider->regenerateAndSetShopId($e->shopId->id);
        }

        // Refreshing the registration contacts external app servers. If one app is unreachable we
        // still want the remaining apps to learn about the shop change, then report all failed apps together.
        /** @var array<string, \Throwable> $failedApps */
        $failedApps = [];

        foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
            $manifest = $this->manifestFactory->createFromApp($app);

            if (!$manifest->getSetup()) {
                continue;
            }

            try {
                $this->appManager->refreshRegistration($app, $manifest, $context);
            } catch (\Throwable $e) {
                $failedApps[$app->getName()] = $e;
            }
        }

        if ($failedApps !== []) {
            throw AppException::reRegistrationFailed(array_keys($failedApps), reset($failedApps));
        }
    }
}
