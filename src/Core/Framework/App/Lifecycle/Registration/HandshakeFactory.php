<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Registration;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\App\Url\VerificationStatus;
use Shopware\Core\Framework\App\Validation\Requirements\SecureUrlValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClient;

/**
 * @internal only for use by the app-system
 *
 * @final
 */
#[Package('framework')]
readonly class HandshakeFactory
{
    public function __construct(
        private string $shopUrl,
        private ShopIdProvider $shopIdProvider,
        private StoreClient $storeClient,
        private string $shopwareVersion,
        private ClockInterface $clock,
        private AppUrlVerifier $appUrlVerifier,
        private SecureUrlValidator $secureUrlValidator,
    ) {
    }

    public function create(Manifest $manifest, AppEntity $app, #[\SensitiveParameter] ?string $currentSecret = null): AppHandshakeInterface
    {
        $setup = $manifest->getSetup();
        $metadata = $manifest->getMetadata();
        $appName = $metadata->getName();

        if (!$setup) {
            throw AppException::registrationFailed(
                $appName,
                \sprintf('No setup for registration provided in manifest for app "%s".', $metadata->getName())
            );
        }

        $privateSecret = $setup->getSecret();

        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (ShopIdChangeSuggestedException $e) {
            throw AppException::registrationFailed(
                $appName,
                $e->getMessage(),
            );
        }

        if ($this->secureUrlValidator->isValidTarget($setup->getRegistrationUrl())) {
            $state = $this->appUrlVerifier->forceVerify($shopId);

            if (!$state->is(VerificationStatus::PASS)) {
                throw AppException::registrationFailed(
                    $appName,
                    \sprintf('APP_URL "%s" is incorrect or does not reach this installation (%s)', $this->shopUrl, $state->info ?? $state->status->name),
                );
            }
        }

        $shopId = $shopId->id;

        // The secret the app currently holds, used to sign the re-registration's previous-signature.
        // Normally this is the stored app_secret; recovery passes in the unconfirmed secret the app may
        // already have switched to.
        $currentAppSecret = $currentSecret ?? $app->getAppSecret();

        if ($privateSecret) {
            return new PrivateHandshake(
                $this->shopUrl,
                $privateSecret,
                $setup->getRegistrationUrl(),
                $metadata->getName(),
                $shopId,
                $this->shopwareVersion,
                $this->clock,
                $currentAppSecret,
            );
        }

        return new StoreHandshake(
            $this->shopUrl,
            $setup->getRegistrationUrl(),
            $metadata->getName(),
            $shopId,
            $this->storeClient,
            $this->shopwareVersion,
            $this->clock,
            $currentAppSecret,
        );
    }
}
