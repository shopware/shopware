<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopIdChangeResolver;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

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
class MoveShopPermanentlyStrategy extends AbstractShopIdChangeStrategy
{
    final public const STRATEGY_NAME = 'move-shop-permanently';

    public function __construct(
        SourceResolver $sourceResolver,
        EntityRepository $appRepository,
        AppRegistrationService $registrationService,
        private readonly ShopIdProvider $shopIdProvider
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
        return 'This is typically the right option if you have permanently moved your shop to a different infrastructure or new environment. Shopware will notify apps (i.e. re-register at the app servers) using the same shop identifier and apps remain installed. Your shop will identify as the same shop as before.';
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

        $this->forEachInstalledApp($context, function (Manifest $manifest, AppEntity $app, Context $context): void {
            $this->reRegisterApp($manifest, $app, $context);
        });
    }
}
