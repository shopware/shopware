<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\JWT\JWTDecoder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Shopware\Core\Framework\Store\InAppPurchase\Services\KeyFetcher;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchase::class)]
class InAppPurchaseTest extends TestCase
{
    use KernelTestBehaviour;

    private StaticSystemConfigService $staticSystemConfigService;

    public function testFlattened(): void
    {
        $iap = $this->createIap();
        static::assertSame(['Extension1-Purchase1', 'Extension1-Purchase2', 'Extension2-Purchase2'], $iap->formatPurchases());
    }

    public function testAll(): void
    {
        $iap = $this->createIap();
        static::assertSame(['Extension1' => ['Purchase1', 'Purchase2'], 'Extension2' => ['Purchase2']], $iap->all());
    }

    public function testIsActive(): void
    {
        $iap = $this->createIap();
        static::assertTrue($iap->isActive('Extension1', 'Purchase1'));
        static::assertTrue($iap->isActive('Extension2', 'Purchase2'));
        static::assertTrue($iap->isActive('Extension1', 'Purchase2'));
        static::assertFalse($iap->isActive('Extension1', 'inactivePurchase'));
    }

    public function testEmpty(): void
    {
        $iap = new InAppPurchase(
            new InAppPurchaseProvider(
                new StaticSystemConfigService(),
                new JWTDecoder(),
                new KeyFetcher(
                    static::getContainer()->get('shopware.store_client'),
                    static::getContainer()->get(StoreRequestOptionsProvider::class),
                    new StaticSystemConfigService(),
                    static::getContainer()->get('logger')
                )
            )
        );

        static::assertFalse($iap->isActive('ExtensionName', 'inactivePurchase'));
        static::assertEmpty($iap->formatPurchases());
    }

    public function testRegisterPurchasesOverridesActivePurchases(): void
    {
        $iap = $this->createIap();
        static::assertTrue($iap->isActive('Extension1', 'Purchase1'));

        $this->staticSystemConfigService->set(InAppPurchaseProvider::CONFIG_STORE_IAP_KEY, json_encode([]));

        $jwt = file_get_contents(__DIR__ . '/_fixtures/replacement-jwt.json');

        $iap->reset();
        $this->staticSystemConfigService->set(InAppPurchaseProvider::CONFIG_STORE_IAP_KEY, $jwt);

        static::assertFalse($iap->isActive('Extension1', 'Purchase1'));
        static::assertTrue($iap->isActive('Extension1', 'Purchase2'));
    }

    private function createIap(): InAppPurchase
    {
        $jwt = file_get_contents(__DIR__ . '/_fixtures/jwt.json');
        static::assertIsString($jwt);

        $jwks = file_get_contents(__DIR__ . '/_fixtures/jwks.json');
        static::assertIsString($jwks);

        $this->staticSystemConfigService = new StaticSystemConfigService([
            'core.store.licenseHost' => 'example.com',
            KeyFetcher::CORE_STORE_JWKS => $jwks,
            InAppPurchaseProvider::CONFIG_STORE_IAP_KEY => $jwt,
        ]);

        return new InAppPurchase(
            new InAppPurchaseProvider(
                $this->staticSystemConfigService,
                new JWTDecoder(),
                new KeyFetcher(
                    static::getContainer()->get('shopware.store_client'),
                    static::getContainer()->get(StoreRequestOptionsProvider::class),
                    $this->staticSystemConfigService,
                    static::getContainer()->get('logger')
                )
            )
        );
    }
}
