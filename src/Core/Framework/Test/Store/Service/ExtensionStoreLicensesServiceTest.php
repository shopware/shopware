<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Exception\LicenseNotFoundException;
use Shopware\Core\Framework\Store\Exception\StoreLicenseDomainMissingException;
use Shopware\Core\Framework\Store\Services\AbstractExtensionStoreLicensesService;
use Shopware\Core\Framework\Store\Services\ExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\ExtensionStoreLicensesService;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Store\Struct\ReviewStruct;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Filesystem\Filesystem;

class ExtensionStoreLicensesServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    /**
     * @var ExtensionStoreLicensesService
     */
    private $extensionLicensesService;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);
        $this->extensionLicensesService = $this->getContainer()->get(AbstractExtensionStoreLicensesService::class);
    }

    public function testGetLicensedExtensionsWithoutDomain(): void
    {
        $this->getContainer()->get(SystemConfigService::class)->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, '');
        static::expectException(StoreLicenseDomainMissingException::class);
        $this->extensionLicensesService->getLicensedExtensions(Context::createDefaultContext());
    }

    public function testGetLicensedExtensions(): void
    {
        $this->getContainer()->get(SystemConfigService::class)->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, 'localhost');

        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200, [], file_get_contents(__DIR__ . '/../_fixtures/responses/licenses.json')));

        $licenses = $this->extensionLicensesService->getLicensedExtensions($this->getContextWithStoreToken());

        static::assertCount(1, $licenses);
        static::assertSame('free', $licenses->first()->getVariant());
    }

    public function testPurchaseExtensionCreatesCartAndProcessesIt(): void
    {
        $this->getContainer()->get(SystemConfigService::class)->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, 'localhost');
        $this->setResponsesToPurchaseExtension();

        $this->extensionLicensesService->purchaseExtension(5, 5, $this->getContextWithStoreToken());

        $appDir = $this->getContainer()->getParameter('shopware.app_dir') . '/TestApp';
        static::assertFileExists($appDir);
        (new Filesystem())->remove($appDir);
    }

    public function testCancelSubscriptionRemovesLicense(): void
    {
        $context = $this->getContextWithStoreToken();
        $this->getContainer()->get(SystemConfigService::class)->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, 'localhost');
        $this->setResponsesToPurchaseExtension();

        $this->extensionLicensesService->purchaseExtension(5, 5, $context);

        $this->setCancelationResponses();

        $licenseCollection = $this->extensionLicensesService->cancelSubscription(1, $context);

        static::assertEquals(
            '/swplatform/licenses?shopwareVersion=___VERSION___&language=en-GB&domain=localhost',
            $this->getRequestHandler()->getLastRequest()->getRequestTarget()
        );
        static::assertEquals(0, $licenseCollection->getTotal());
    }

    public function testCancelSubscriptionNotInstalled(): void
    {
        $this->getContainer()->get(SystemConfigService::class)->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, 'localhost');
        $context = $this->getContextWithStoreToken();

        $this->setCancelationResponses();

        $licenseCollection = $this->extensionLicensesService->cancelSubscription(1, $context);

        static::assertEquals(
            '/swplatform/licenses?shopwareVersion=___VERSION___&language=en-GB&domain=localhost',
            $this->getRequestHandler()->getLastRequest()->getRequestTarget()
        );
        static::assertEquals(0, $licenseCollection->getTotal());
    }

    public function testCreateRating(): void
    {
        $this->extensionLicensesService->rateLicensedExtension(new ReviewStruct(), $this->getContextWithStoreToken());
    }

    public function testCancelSubscriptionThrowsExceptionIfLicenseIsNotFound(): void
    {
        $this->getContainer()->get(SystemConfigService::class)->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, 'localhost');
        $this->setLicensesRequest(\file_get_contents(__DIR__ . '/../_fixtures/responses/licenses.json'));

        static::expectException(LicenseNotFoundException::class);
        $this->extensionLicensesService->cancelSubscription(-200, $this->getContextWithStoreToken());
    }

    private function getContextWithStoreToken(): Context
    {
        $userId = Uuid::randomHex();

        $data = [
            [
                'id' => $userId,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => 'foobar',
                'password' => 'asdasdasdasd',
                'firstName' => 'Foo',
                'lastName' => 'Bar',
                'email' => 'foo@bar.com',
                'storeToken' => Uuid::randomHex(),
                'admin' => true,
                'aclRoles' => [],
            ],
        ];

        $this->getContainer()->get('user.repository')->create($data, Context::createDefaultContext());
        $source = new AdminApiSource($userId);
        $source->setIsAdmin(true);

        return Context::createDefaultContext($source);
    }

    private function setResponsesToPurchaseExtension(): void
    {
        $exampleCart = file_get_contents(__DIR__ . '/../_fixtures/responses/example-cart.json');

        // createCart will respond with a cart
        $this->getRequestHandler()->append(new Response(200, [], $exampleCart));

        // processCart will return an Created Response with no body
        $this->getRequestHandler()->append(new Response(201, [], null));

        // return path to app files from install extension
        $this->getRequestHandler()->append(new Response(200, [], '{"location": "http://localhost/my.zip"}'));
        $this->getRequestHandler()->append(new Response(200, [], file_get_contents(__DIR__ . '/../_fixtures/TestApp.zip')));
    }

    private function setLicensesRequest(string $licenseBody): void
    {
        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200, [], $licenseBody));
    }

    private function setCancelationResponses(): void
    {
        $licenses = \json_decode(\file_get_contents(__DIR__ . '/../_fixtures/responses/licenses.json'), true);
        $licenses[0]['extension']['name'] = 'TestApp';

        $this->setLicensesRequest(\json_encode($licenses));
        $this->getRequestHandler()->append(new Response(204));

        unset($licenses[0]);
        $this->getRequestHandler()->append(
            new Response(
                200,
                [ExtensionDataProvider::HEADER_NAME_TOTAL_COUNT => '0'],
                \json_encode($licenses)
            )
        );
    }
}
