<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 *
 * @group skip-paratest
 */
class ExtensionDataProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;
    use ExtensionBehaviour;

    private AbstractExtensionDataProvider $extensionDataProvider;

    private Context $context;

    protected function setUp(): void
    {
        $this->extensionDataProvider = $this->getContainer()->get(AbstractExtensionDataProvider::class);
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
        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200, [], '[]'));

        $installedExtensions = $this->extensionDataProvider->getInstalledExtensions($this->context, true);
        $installedExtension = $installedExtensions->get('TestApp');

        static::assertInstanceOf(ExtensionStruct::class, $installedExtension);
        static::assertNull($installedExtension->getId());
        static::assertEquals('Swag App Test', $installedExtension->getLabel());
    }

    public function testGetAppEntityFromTechnicalName(): void
    {
        static::assertInstanceOf(AppEntity::class, $this->extensionDataProvider->getAppEntityFromTechnicalName('TestApp', $this->context));
    }

    public function testGetAppEntityFromId(): void
    {
        $installedApp = $this->extensionDataProvider->getAppEntityFromTechnicalName('TestApp', $this->context);

        $app = $this->extensionDataProvider->getAppEntityFromId($installedApp->getId(), $this->context);
        static::assertEquals(
            $installedApp,
            $app
        );
    }

    public function testGetAppEntityFromTechnicalNameThrowsIfExtensionCantBeFound(): void
    {
        static::expectException(ExtensionNotFoundException::class);
        $this->extensionDataProvider->getAppEntityFromTechnicalName(Uuid::randomHex(), $this->context);
    }

    public function testGetAppEntityFromIdThrowsIfExtensionCantBeFound(): void
    {
        static::expectException(ExtensionNotFoundException::class);
        $this->extensionDataProvider->getAppEntityFromId(Uuid::randomHex(), $this->context);
    }

    public function testItLoadsRemoteExtensions(): void
    {
        $this->getContainer()->get(SystemConfigService::class)->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, 'localhost');
        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200, [], '{"data":[]}'));
        $this->getRequestHandler()->append(new Response(200, [], (string) file_get_contents(__DIR__ . '/../_fixtures/responses/my-licenses.json')));

        $installedExtensions = $this->extensionDataProvider->getInstalledExtensions($this->context, true);
        $installedExtensions = $installedExtensions->filter(fn (ExtensionStruct $extension) => $extension->getName() !== 'SwagCommercial');
        static::assertCount(7, $installedExtensions);
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

        $this->getRequestHandler()->append(new Response(200, [], (string) file_get_contents(__DIR__ . '/../_fixtures/responses/my-licenses.json')));

        $installedExtensions = $this->extensionDataProvider->getInstalledExtensions($this->context, true);
        $installedExtensions = $installedExtensions->filter(fn (ExtensionStruct $extension) => $extension->getName() !== 'SwagCommercial');
        static::assertCount(1, $installedExtensions);
    }

    public function testItReturnsLocalExtensionsIfDomainIsNotSet(): void
    {
        $this->setLicenseDomain(null);

        $this->getRequestHandler()->append(
            $this->getDomainMissingResponse(),
            $this->getDomainMissingResponse()
        );

        $installedExtensions = $this->extensionDataProvider->getInstalledExtensions($this->context, true);
        $installedExtensions = $installedExtensions->filter(fn (ExtensionStruct $extension) => $extension->getName() !== 'SwagCommercial');

        static::assertCount(1, $installedExtensions);

        $installedExtension = $installedExtensions->get('TestApp');

        static::assertInstanceOf(ExtensionStruct::class, $installedExtension);
        static::assertNull($installedExtension->getId());
        static::assertEquals('Swag App Test', $installedExtension->getLabel());
    }

    private function getDomainMissingResponse(): ResponseInterface
    {
        return new Response(400, [], \json_encode([
            'code' => 'ShopwarePlatformException-3',
            'detail' => 'REQUEST_PARAMETER_DOMAIN_NOT_GIVEN',
        ], \JSON_THROW_ON_ERROR));
    }
}
