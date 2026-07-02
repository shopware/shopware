<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\AdminAuth\Provider\ConfigProviderLoader;
use Shopware\Core\Framework\AdminAuth\Provider\DalProviderLoader;
use Shopware\Core\Framework\AdminAuth\Provider\ProviderRegistry;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ProviderRegistry::class)]
class ProviderRegistryTest extends TestCase
{
    public function testYamlProvidersTakePrecedenceOverDatabaseProviders(): void
    {
        $dalLoader = $this->createMock(DalProviderLoader::class);
        $dalLoader->expects($this->never())->method('load');

        $registry = new ProviderRegistry(
            new ConfigProviderLoader(['corp_okta' => ['label' => 'Corporate SSO', 'client_id' => 'a', 'client_secret' => 'b']]),
            $dalLoader
        );

        $providers = $registry->all();

        static::assertCount(1, $providers);
        static::assertSame('yaml:corp_okta', $providers[0]->providerKey);
        static::assertTrue($registry->isManagedByConfig());
    }

    public function testDatabaseProvidersApplyWithoutYamlProviders(): void
    {
        $provider = $this->dbProvider(active: true);

        $dalLoader = $this->createMock(DalProviderLoader::class);
        $dalLoader->method('load')->willReturn([$provider]);

        $registry = new ProviderRegistry(new ConfigProviderLoader(), $dalLoader);

        static::assertSame([$provider], $registry->all());
        static::assertFalse($registry->isManagedByConfig());
    }

    public function testAllFiltersInactiveProviders(): void
    {
        $active = $this->dbProvider(active: true);
        $inactive = $this->dbProvider(active: false);

        $dalLoader = $this->createMock(DalProviderLoader::class);
        $dalLoader->method('load')->willReturn([$inactive, $active]);

        $registry = new ProviderRegistry(new ConfigProviderLoader(), $dalLoader);

        static::assertSame([$active], $registry->all());
    }

    public function testByIdFindsProvidersCaseInsensitivelyIncludingInactiveOnes(): void
    {
        $inactive = $this->dbProvider(active: false);

        $dalLoader = $this->createMock(DalProviderLoader::class);
        $dalLoader->method('load')->willReturn([$inactive]);

        $registry = new ProviderRegistry(new ConfigProviderLoader(), $dalLoader);

        static::assertSame($inactive, $registry->byId($inactive->id));
        static::assertSame($inactive, $registry->byId(strtoupper($inactive->id)));
        static::assertNull($registry->byId(Uuid::randomHex()));
    }

    public function testYamlProviderIdsAreStableAcrossRegistryInstances(): void
    {
        $config = ['corp_okta' => ['label' => 'Corporate SSO', 'client_id' => 'a', 'client_secret' => 'b']];
        $dalLoader = $this->createMock(DalProviderLoader::class);

        $first = (new ProviderRegistry(new ConfigProviderLoader($config), $dalLoader))->all()[0];
        $second = (new ProviderRegistry(new ConfigProviderLoader($config), $dalLoader))->all()[0];

        static::assertSame($first->id, $second->id);
        static::assertNotNull((new ProviderRegistry(new ConfigProviderLoader($config), $dalLoader))->byId($first->id));
    }

    public function testAdminUiIsEnabledOnlyWithoutYamlProvidersAndWithTheConfigFlag(): void
    {
        $dalLoader = $this->createMock(DalProviderLoader::class);
        $yamlConfig = ['corp_okta' => ['label' => 'SSO', 'client_id' => 'a', 'client_secret' => 'b']];

        $default = new ProviderRegistry(new ConfigProviderLoader(), $dalLoader, true);
        static::assertTrue($default->isAdminUiEnabled());

        $uiDisabled = new ProviderRegistry(new ConfigProviderLoader(), $dalLoader, false);
        static::assertFalse($uiDisabled->isAdminUiEnabled());

        $managed = new ProviderRegistry(new ConfigProviderLoader($yamlConfig), $dalLoader, true);
        static::assertFalse($managed->isAdminUiEnabled());
    }

    private function dbProvider(bool $active): AdminAuthProvider
    {
        $id = Uuid::randomHex();

        return new AdminAuthProvider(
            id: $id,
            providerKey: $id,
            label: 'DB Provider',
            clientId: 'client',
            clientSecret: 'secret',
            active: $active,
        );
    }
}
