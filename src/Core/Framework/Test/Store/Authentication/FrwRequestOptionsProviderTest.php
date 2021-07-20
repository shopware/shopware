<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Authentication;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Authentication\FrwRequestOptionsProvider;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class FrwRequestOptionsProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private FrwRequestOptionsProvider $optionsProvider;

    public function setUp(): void
    {
        $this->optionsProvider = $this->getContainer()->get(FrwRequestOptionsProvider::class);
    }

    public function testGetAuthenticationHeadersOverridesStoreToken(): void
    {
        $context = $this->createAdminStoreContext();

        $headers = $this->optionsProvider->getAuthenticationHeader($context);

        static::assertArrayHasKey('X-Shopware-Token', $headers);
        static::assertEquals(
            $this->getStoreTokenFromContext($context),
            $headers['X-Shopware-Token']
        );
    }
}
