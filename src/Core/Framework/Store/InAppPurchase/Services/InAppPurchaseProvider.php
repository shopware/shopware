<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\InAppPurchase\Services;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\JWT\Constraints\HasValidRSAJWKSignature;
use Shopware\Core\Framework\JWT\Constraints\MatchesLicenceDomain;
use Shopware\Core\Framework\JWT\JWTDecoder;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\JWT\Struct\JWKStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 *
 * @phpstan-import-type JSONWebKey from JWKStruct
 */
#[Package('checkout')]
final readonly class InAppPurchaseProvider
{
    public const CONFIG_STORE_IAP_KEY = 'core.store.iapKey';

    public function __construct(
        private SystemConfigService $systemConfig,
        private JWTDecoder $decoder,
        private KeyFetcher $keyFetcher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function getPurchases(): array
    {
        $purchases = $this->getPurchasesJWT();

        return $this->filterActive($this->decodePurchases($purchases));
    }

    /**
     * @return array<string, string>
     */
    public function getPurchasesJWT(): array
    {
        $purchases = \json_decode($this->systemConfig->getString(self::CONFIG_STORE_IAP_KEY), true);

        if (\is_array($purchases)) {
            return $purchases;
        }

        return [];
    }

    /**
     * @param array<string, string> $encodedPurchases
     *
     * @return array<string, array<int, DecodedPurchasesCollectionStruct>>
     */
    private function decodePurchases(array $encodedPurchases, bool $retried = false): array
    {
        if ($encodedPurchases === []) {
            return [];
        }

        $context = Context::createDefaultContext();

        try {
            $jwks = $this->keyFetcher->getKey($context, $retried);
        } catch (AppException|StoreException $e) { // only StoreException::jwksNotFound() is thrown
            $this->logger->error('Unable to decode In-App purchases: {message}', ['message' => $e->getMessage()]);

            return [];
        }

        $signatureValidator = new HasValidRSAJWKSignature($jwks);
        $domainValidator = new MatchesLicenceDomain($this->systemConfig);

        $decodedPurchases = [];

        foreach ($encodedPurchases as $extensionName => $purchaseJwt) {
            try {
                $this->decoder->validate($purchaseJwt, $signatureValidator, $domainValidator);
                $decodedPurchases[$extensionName][] = DecodedPurchasesCollectionStruct::fromArray($this->decoder->decode($purchaseJwt));
            } catch (JWTException $e) {
                if ($retried) {
                    $this->logger->error('Unable to decode In-App purchases for extension "{extension}": {message}', ['extension' => $extensionName, 'message' => $e->getMessage()]);

                    return [];
                }

                return $this->decodePurchases($encodedPurchases, true);
            }
        }

        return $decodedPurchases;
    }

    /**
     * @param array<string, array<int, DecodedPurchasesCollectionStruct>> $decodePurchases
     *
     * @return array<string, list<string>>
     */
    private function filterActive(array $decodePurchases): array
    {
        $activePurchases = [];

        foreach ($decodePurchases as $extensionName => $extensionPurchases) {
            foreach ($extensionPurchases as $purchases) {
                foreach ($purchases as $purchase) {
                    if (\is_string($purchase->nextBookingDate) && new \DateTime($purchase->nextBookingDate) < new \DateTime()) {
                        continue;
                    }

                    $activePurchases[$extensionName][] = $purchase->identifier;
                }
            }
        }

        return $activePurchases;
    }
}
