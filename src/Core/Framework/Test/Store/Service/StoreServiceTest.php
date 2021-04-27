<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\Framework\Store\Struct\ShopUserTokenStruct;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class StoreServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private StoreService $storeService;

    public function setUp(): void
    {
        $this->storeService = $this->getContainer()->get(StoreService::class);
    }

    public function testGetDefaultQueryParametersIncludesLicenseDomainIfSet(): void
    {
        $this->setLicenseDomain('test-shop');

        $queryParameters = $this->storeService->getDefaultQueryParameters('en_GB');

        static::assertEquals([
            'shopwareVersion' => $this->getShopwareVersion(),
            'language' => 'en_GB',
            'domain' => 'test-shop',
        ], $queryParameters);
    }

    public function testGetDefaultQueryParametersRemovesEmptyQueries(): void
    {
        $queries = $this->storeService->getDefaultQueryParameters('en_GB', false);

        static::assertArrayHasKey('language', $queries);
        static::assertEquals('en_GB', $queries['language']);

        static::assertArrayNotHasKey('domain', $queries);

        static::assertArrayHasKey('shopwareVersion', $queries);
        static::assertEquals($this->getShopwareVersion(), $queries['shopwareVersion']);
    }

    public function testGetLanguageFromContextReturnsEnglishIfContextIsNotAdminApiContext(): void
    {
        $language = $this->storeService->getLanguageByContext(Context::createDefaultContext());

        static::assertEquals('en-GB', $language);
    }

    public function testGetLanguageFromContextReturnsEnglishForIntegrations(): void
    {
        $context = new Context(new AdminApiSource(null, Uuid::randomHex()));

        $language = $this->storeService->getLanguageByContext($context);

        static::assertEquals('en-GB', $language);
    }

    public function testGetLanguageFromContextReturnsLocaleFromUser(): void
    {
        $adminStoreContext = $this->createAdminStoreContext();

        $language = $this->storeService->getLanguageByContext($adminStoreContext);

        $criteria = new Criteria([$adminStoreContext->getSource()->getUserId()]);
        $criteria->addAssociation('locale');

        $storeUser = $this->getUserRepository()->search($criteria, $adminStoreContext)->first();

        static::assertEquals($storeUser->getLocale()->getCode(), $language);
    }

    public function testUpdateStoreToken(): void
    {
        $adminStoreContext = $this->createAdminStoreContext();

        $newToken = 'updated-store-token';
        $accessTokenStruct = new AccessTokenStruct();
        $accessTokenStruct->setShopUserToken((new ShopUserTokenStruct())->assign(['token' => $newToken]));

        $this->storeService->updateStoreToken(
            $adminStoreContext,
            $accessTokenStruct
        );

        $criteria = new Criteria([$adminStoreContext->getSource()->getuserId()]);

        $updatedUser = $this->getUserRepository()->search($criteria, $adminStoreContext)->first();

        static::assertEquals('updated-store-token', $updatedUser->getStoreToken());
    }
}
