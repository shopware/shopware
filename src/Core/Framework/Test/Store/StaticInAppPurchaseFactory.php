<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store;

use GuzzleHttp\Client;
use Shopware\Core\Framework\JWT\JWTDecoder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Shopware\Core\Framework\Store\InAppPurchase\Services\KeyFetcher;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpKernel\Log\Logger;

/**
 * @internal
 */
#[Package('checkout')]
class StaticInAppPurchaseFactory
{
    /**
     * @param array<string, array<int, string>> $activePurchases ['extensionName' => ...purchases]
     */
    public static function createWithFeatures(array $activePurchases = []): InAppPurchase
    {
        $inAppPurchase = new InAppPurchase(
            new InAppPurchaseProvider(
                new StaticSystemConfigService([
                    InAppPurchaseProvider::CONFIG_STORE_IAP_KEY => \json_encode(self::purchasesToJWTS($activePurchases), \JSON_THROW_ON_ERROR),
                ]),
                new JWTDecoder(),
                new KeyFetcher(
                    new Client(),
                    new class extends StoreRequestOptionsProvider {
                        public function __construct()
                        {
                        }
                    },
                    new StaticSystemConfigService(),
                    new class extends Logger {
                        public function __construct()
                        {
                        }
                    }
                )
            )
        );

        $reflection = new \ReflectionProperty(InAppPurchase::class, 'activePurchases');
        $reflection->setValue($inAppPurchase, $activePurchases);

        return $inAppPurchase;
    }

    /**
     * @param array<string, array<int, string>> $activePurchases
     *
     * @return array<string, string>
     */
    private static function purchasesToJWTS(array $activePurchases): array
    {
        return \array_map(
            /** @var array<int, string> $purchases */
            static fn (array $purchases) => \md5(\json_encode($purchases, \JSON_THROW_ON_ERROR)),
            $activePurchases
        );
    }
}
