<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\Services;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
class ExtensionDataProviderTest extends TestCase
{
    use ExtensionBehaviour;
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private const STORE_EXTENSION_NAMES = [
        'SwagApp',
        'SwagSuperPlugin',
        'SwagCore',
        'SwagEnterpriseSearch',
        'SwagCustomProducts',
        'SwagLicense',
    ];

    private AbstractExtensionDataProvider $extensionDataProvider;

    private Context $context;

    protected function setUp(): void
    {
        $this->extensionDataProvider = static::getContainer()->get(AbstractExtensionDataProvider::class);
        $this->context = $this->createAdminStoreContext();

        $this->installApp(__DIR__ . '/../_fixtures/TestApp');
    }

    protected function tearDown(): void
    {
        $this->removeApp(__DIR__ . '/../_fixtures/TestApp');
    }

    public function testItReturnsInstalledAppsAsExtensionCollection(): void
    {
        $this->setLicenseDomain('localhost');
        $this->getStoreRequestHandler()->reset();
        $this->getStoreRequestHandler()->append(new Response(200, [], '[]'));
        $this->getStoreRequestHandler()->append(new Response(200, [], '[]'));

        $installedExtensions = $this->extensionDataProvider->getInstalledExtensions($this->context);
        $installedExtension = $installedExtensions->get('TestApp');

        static::assertInstanceOf(ExtensionStruct::class, $installedExtension);
        static::assertNull($installedExtension->getId());
        static::assertSame('Swag App Test', $installedExtension->getLabel());
    }

    public function testGetAppEntityFromTechnicalName(): void
    {
        $app = $this->extensionDataProvider->getAppEntityFromTechnicalName('TestApp', $this->context);

        static::assertSame('TestApp', $app->getName());
    }

    public function testGetAppEntityFromId(): void
    {
        $installedApp = $this->extensionDataProvider->getAppEntityFromTechnicalName('TestApp', $this->context);

        $app = $this->extensionDataProvider->getAppEntityFromId($installedApp->getId(), $this->context);
        static::assertEquals($installedApp, $app);
    }

    public function testGetAppEntityFromTechnicalNameThrows(): void
    {
        $this->expectExceptionObject(StoreException::extensionNotFoundFromTechnicalName('testName'));
        $this->extensionDataProvider->getAppEntityFromTechnicalName('testName', $this->context);
    }

    public function testGetAppEntityFromIdThrows(): void
    {
        $id = Uuid::randomHex();

        $this->expectExceptionObject(StoreException::extensionNotFoundFromId($id));
        $this->extensionDataProvider->getAppEntityFromId($id, $this->context);
    }

    public function testItLoadsRemoteExtensions(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, 'localhost');
        $this->getStoreRequestHandler()->reset();
        $this->getStoreRequestHandler()->append(new Response(200, [], '{"data":[]}'));
        $this->getStoreRequestHandler()->append(new Response(200, [], $this->getLicencesJson()));

        $installedExtensions = $this->extensionDataProvider->getInstalledExtensions($this->context);

        static::assertInstanceOf(ExtensionStruct::class, $installedExtensions->get('TestApp'));
        foreach (self::STORE_EXTENSION_NAMES as $extensionName) {
            static::assertTrue($installedExtensions->has($extensionName), \sprintf('Failed asserting that store extension "%s" was loaded.', $extensionName));
        }
    }

    public function testItReturnsLocalExtensionsIfUserIsNotLoggedIn(): void
    {
        $contextSource = $this->context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $contextSource);

        $this->getUserRepository()->update([
            [
                'id' => $contextSource->getUserId(),
                'storeToken' => null,
            ],
        ], Context::createDefaultContext());

        $this->getStoreRequestHandler()->append(
            $this->getStoreUnavailableResponse(),
            $this->getStoreUnavailableResponse(),
        );

        $installedExtensions = $this->extensionDataProvider->getInstalledExtensions($this->context);

        static::assertInstanceOf(ExtensionStruct::class, $installedExtensions->get('TestApp'));
        $this->assertStoreExtensionsAreNotLoaded($installedExtensions);
    }

    public function testItReturnsLocalExtensionsIfDomainIsNotSet(): void
    {
        $this->setLicenseDomain(null);

        $this->getStoreRequestHandler()->append(
            $this->getDomainMissingResponse(),
            $this->getDomainMissingResponse()
        );

        $installedExtensions = $this->extensionDataProvider->getInstalledExtensions($this->context);

        $this->assertStoreExtensionsAreNotLoaded($installedExtensions);

        $installedExtension = $installedExtensions->get('TestApp');

        static::assertInstanceOf(ExtensionStruct::class, $installedExtension);
        static::assertNull($installedExtension->getId());
        static::assertSame('Swag App Test', $installedExtension->getLabel());
    }

    public function testItIgnoresManagedApps(): void
    {
        $contextSource = $this->context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $contextSource);

        $context = Context::createDefaultContext();
        $this->getUserRepository()->update([
            [
                'id' => $contextSource->getUserId(),
                'storeToken' => null,
            ],
        ], $context);

        // update apps and set managed = true
        $appRepository = static::getContainer()->get('app.repository');
        $ids = $appRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('name', 'TestApp')), $context);

        $appRepository->update(
            [
                ['id' => $ids->firstId(), 'selfManaged' => true],
            ],
            $context
        );

        // we must remove it so that it is not considered as a local app
        $this->removeApp(__DIR__ . '/../_fixtures/TestApp');

        $this->getStoreRequestHandler()->append(new Response(200, [], '[]'));
        $this->getStoreRequestHandler()->append(new Response(200, [], $this->getLicencesJson()));

        $installedExtensions = $this->extensionDataProvider->getInstalledExtensions($this->context);
        static::assertFalse($installedExtensions->has('TestApp'));
    }

    private function assertStoreExtensionsAreNotLoaded(ExtensionCollection $extensions): void
    {
        foreach (self::STORE_EXTENSION_NAMES as $extensionName) {
            static::assertFalse($extensions->has($extensionName), \sprintf('Failed asserting that store extension "%s" was not loaded.', $extensionName));
        }
    }

    private function getLicencesJson(): string
    {
        $json = file_get_contents(__DIR__ . '/../_fixtures/responses/my-licenses.json');
        static::assertIsString($json, 'Could not read my-licenses.json file');

        return $json;
    }

    private function getDomainMissingResponse(): ResponseInterface
    {
        return new Response(400, [], \json_encode([
            'code' => 'ShopwarePlatformException-3',
            'detail' => 'REQUEST_PARAMETER_DOMAIN_NOT_GIVEN',
        ], \JSON_THROW_ON_ERROR));
    }

    private function getStoreUnavailableResponse(): ResponseInterface
    {
        return new Response(400);
    }
}
