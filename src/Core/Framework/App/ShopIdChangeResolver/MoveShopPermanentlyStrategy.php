<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopIdChangeResolver;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
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
        private readonly EntityRepository $appRepository,
        private readonly AppManager $appManager,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly LoggerInterface $logger
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
        /** @var list<string> $failedApps */
        $failedApps = [];

        foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
            try {
                $this->appManager->refreshRegistration($app, $context);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to re-register app after shop ID change.', [
                    'appName' => $app->getName(),
                    'exception' => $e,
                ]);

                $failedApps[] = $app->getName();
            }
        }

        if ($failedApps !== []) {
            throw AppException::shopMoveFailed($failedApps);
        }
    }
}
