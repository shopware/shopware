<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Services\AbstractExtensionStoreLicensesService;
use Shopware\Core\Framework\Store\Services\ExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\ExtensionStoreLicensesService;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Store\Struct\ReviewStruct;
use Shopware\Core\Framework\Test\Store\StoreClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class ExtensionStoreLicensesServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    /**
     * @var ExtensionStoreLicensesService
     */
    private $extensionLicensesService;

    protected function setUp(): void
    {
        $this->extensionLicensesService = $this->getContainer()->get(AbstractExtensionStoreLicensesService::class);
    }

    public function testCancelSubscriptionNotInstalled(): void
    {
        $this->getContainer()->get(SystemConfigService::class)->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, 'localhost');
        $context = $this->getContextWithStoreToken();

        $this->setCancelationResponses();

        $this->extensionLicensesService->cancelSubscription(1, $context);

        $lastRequest = $this->getRequestHandler()->getLastRequest();
        static::assertEquals(
            '/swplatform/pluginlicenses/1/cancel',
            $lastRequest->getUri()->getPath()
        );

        static::assertEquals(
            [
                'shopwareVersion' => '___VERSION___',
                'language' => 'en-GB',
                'domain' => 'localhost',
            ],
            Query::parse($lastRequest->getUri()->getQuery())
        );
    }

    public function testCreateRating(): void
    {
        $this->getRequestHandler()->append(new Response(200, [], null));
        $review = new ReviewStruct();
        $review->setExtensionId(5);
        $this->extensionLicensesService->rateLicensedExtension($review, $this->getContextWithStoreToken());
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

    private function setLicensesRequest(string $licenseBody): void
    {
        $this->getRequestHandler()->reset();
        $this->getRequestHandler()->append(new Response(200, [], $licenseBody));
    }

    private function setCancelationResponses(): void
    {
        $licenses = json_decode(file_get_contents(__DIR__ . '/../_fixtures/responses/licenses.json'), true, 512, \JSON_THROW_ON_ERROR);
        $licenses[0]['extension']['name'] = 'TestApp';

        $this->setLicensesRequest(json_encode($licenses, \JSON_THROW_ON_ERROR));
        $this->getRequestHandler()->append(new Response(204));

        unset($licenses[0]);
        $this->getRequestHandler()->append(
            new Response(
                200,
                [ExtensionDataProvider::HEADER_NAME_TOTAL_COUNT => '0'],
                json_encode($licenses, \JSON_THROW_ON_ERROR)
            )
        );
    }
}
