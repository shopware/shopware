<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Authentication;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceUserException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class StoreRequestOptionsProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private AbstractStoreRequestOptionsProvider $storeRequestOptionsProvider;

    private SystemConfigService $systemConfigService;

    private Context $storeContext;

    public function setUp(): void
    {
        $this->storeRequestOptionsProvider = $this->getContainer()->get(StoreRequestOptionsProvider::class);
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->storeContext = $this->createAdminStoreContext();
    }

    public function testGetAuthenticationHeadersHasUserStoreTokenAndShopSecret(): void
    {
        $shopSecret = 'im-a-super-safe-secret';

        $this->setShopSecret($shopSecret);
        $headers = $this->storeRequestOptionsProvider->getAuthenticationHeader($this->storeContext);

        static::assertEquals([
            'X-Shopware-Platform-Token' => $this->getStoreTokenFromContext($this->storeContext),
            'X-Shopware-Shop-Secret' => $shopSecret,
        ], $headers);
    }

    public function testGetAuthenticationHeadersUsesFirstStoreTokenFoundIfContextIsSystemSource(): void
    {
        $shopSecret = 'im-a-super-safe-secret';

        $this->setShopSecret($shopSecret);
        $headers = $this->storeRequestOptionsProvider->getAuthenticationHeader(Context::createDefaultContext());

        static::assertEquals([
            'X-Shopware-Platform-Token' => $this->getStoreTokenFromContext($this->storeContext),
            'X-Shopware-Shop-Secret' => $shopSecret,
        ], $headers);
    }

    public function testGetAuthenticationHeadersThrowsForIntegrations(): void
    {
        $context = new Context(new AdminApiSource(null, Uuid::randomHex()));

        static::expectException(InvalidContextSourceUserException::class);
        $this->storeRequestOptionsProvider->getAuthenticationHeader($context);
    }

    public function testGetDefaultQueriesReturnsLanguageIfGiven(): void
    {
        $queries = $this->storeRequestOptionsProvider->getDefaultQueryParameters(null, 'de-CH');

        static::assertArrayHasKey('language', $queries);
        static::assertEquals('de-CH', $queries['language']);
    }

    public function testGetDefaultQueriesReturnsLanguageFromContext(): void
    {
        $queries = $this->storeRequestOptionsProvider->getDefaultQueryParameters($this->storeContext);

        static::assertArrayHasKey('language', $queries);
        static::assertEquals(
            $this->getLanguageFromContext($this->storeContext),
            $queries['language']
        );
    }

    public function testGetDefaultQueriesReturnsShopwareVersion(): void
    {
        $queries = $this->storeRequestOptionsProvider->getDefaultQueryParameters($this->storeContext);

        static::assertArrayHasKey('shopwareVersion', $queries);
        static::assertEquals($this->getShopwareVersion(), $queries['shopwareVersion']);
    }

    public function testGetDefaultQueriesDoesNotHaveDomainSetIfLicenseDomainIsNull(): void
    {
        $this->setLicenseDomain(null);

        $queries = $this->storeRequestOptionsProvider->getDefaultQueryParameters($this->storeContext);

        static::assertArrayNotHasKey('domain', $queries);
    }

    public function testGetDefaultQueriesWithLicenseDomain(): void
    {
        $this->setLicenseDomain('new-license-domain');

        $queries = $this->storeRequestOptionsProvider->getDefaultQueryParameters($this->storeContext);

        static::assertArrayHasKey('domain', $queries);
        static::assertEquals('new-license-domain', $queries['domain']);
    }

    private function getLanguageFromContext(Context $context): string
    {
        $userId = $context->getSource()->getUserId();

        $criteria = (new Criteria([$userId]))->addAssociation('locale');

        $user = $this->getUserRepository()->search($criteria, $context)->first();

        return $user->getLocale()->getCode();
    }
}
