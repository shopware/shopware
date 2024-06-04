<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ScriptControllerTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testGetApiEndpoint(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');

        $response = $this->request('GET', '/storefront/script/json-response', []);
        static::assertNotFalse($response->getContent());

        $body = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($body, true));

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('storefront-json-response', $traces);
        static::assertCount(1, $traces['storefront-json-response']);
        static::assertSame('some debug information', $traces['storefront-json-response'][0]['output'][0]);

        static::assertArrayHasKey('foo', $body);
        static::assertEquals('bar', $body['foo']);
    }

    public function testGetApiEndpointWithSlashInHookName(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');

        $response = $this->request('GET', '/storefront/script/json/response', []);
        static::assertNotFalse($response->getContent());

        $body = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($body, true));

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('storefront-json-response', $traces);
        static::assertCount(1, $traces['storefront-json-response']);
        static::assertSame('some debug information', $traces['storefront-json-response'][0]['output'][0]);

        static::assertArrayHasKey('foo', $body);
        static::assertEquals('bar', $body['foo']);
    }

    public function testPostApiEndpoint(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');

        $response = $this->request(
            'POST',
            '/storefront/script/json-response',
            $this->tokenize('frontend.script_endpoint', [])
        );

        static::assertNotFalse($response->getContent());

        $body = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($body, true));

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('storefront-json-response', $traces);
        static::assertCount(1, $traces['storefront-json-response']);
        static::assertSame('some debug information', $traces['storefront-json-response'][0]['output'][0]);

        static::assertArrayHasKey('foo', $body);
        static::assertEquals('bar', $body['foo']);
    }

    public function testRenderTemplate(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');
        $ids = new IdsCollection();
        $this->createProducts($ids);

        $response = $this->request(
            'GET',
            '/storefront/script/render?product-id=' . $ids->get('p1'),
            []
        );

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertStringContainsString('My Test-Product', $response->getContent());
        static::assertSame('text/plain; charset=UTF-8', $response->headers->get('content-type'));
    }

    public function testRedirectResponseTemplate(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');
        $ids = new IdsCollection();
        $this->createProducts($ids);

        $response = $this->request(
            'GET',
            '/storefront/script/redirect-response?product-id=' . $ids->get('p1'),
            []
        );

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('/detail/' . $ids->get('p1'), $response->headers->get('location'));
    }

    #[DataProvider('ensureLoginProvider')]
    public function testEnsureLogin(bool $login, bool $isGuest, bool $allowGuest, int $expectedStatus, string $expectedResponse): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps');

        if ($login) {
            $browser = $this->registerInBrowser($isGuest);
        } else {
            $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        }

        $browser->request(
            'GET',
            EnvironmentHelper::getVariable('APP_URL') . '/storefront/script/ensure-login?allow-guest=' . (int) $allowGuest
        );
        $response = $browser->getResponse();

        static::assertNotFalse($response->getContent());
        static::assertSame($expectedStatus, $response->getStatusCode());
        static::assertStringContainsString($expectedResponse, $response->getContent());
    }

    public static function ensureLoginProvider(): \Generator
    {
        yield 'Not logged in' => [
            false,
            false,
            false,
            Response::HTTP_FORBIDDEN,
            'Customer is not logged in.',
        ];

        yield 'Logged in as guest, but guest not allowed' => [
            true,
            true,
            false,
            Response::HTTP_FORBIDDEN,
            'Customer is not logged in.',
        ];

        yield 'Logged in as guest and guest is allowed' => [
            true,
            true,
            true,
            Response::HTTP_OK,
            'Hello, Max Mustermann',
        ];

        yield 'Logged in and guest is allowed' => [
            true,
            false,
            true,
            Response::HTTP_OK,
            'Hello, Max Mustermann',
        ];

        yield 'Logged in and guest is not allowed' => [
            true,
            false,
            false,
            Response::HTTP_OK,
            'Hello, Max Mustermann',
        ];
    }

    private function createProducts(IdsCollection $ids): void
    {
        $product1 = (new ProductBuilder($ids, 'p1'))
            ->price(100)
            ->name('My Test-Product')
            ->manufacturer('m1')
            ->variant(
                (new ProductBuilder($ids, 'v1.1'))
                    ->build()
            );

        $salesChannelIds = $this->getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');
        foreach ($salesChannelIds as $salesChannelId) {
            $product1->visibility($salesChannelId);
        }

        $this->getContainer()->get('product.repository')->create([
            $product1->build(),
        ], Context::createDefaultContext());
    }

    private function registerInBrowser(bool $isGuest): KernelBrowser
    {
        $data = $this->getRegistrationData($isGuest);

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/register',
            $this->tokenize('frontend.account.register.save', $data)
        );
        $response = $browser->getResponse();

        static::assertNotFalse($response->getContent());
        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        return $browser;
    }

    /**
     * @return array<string, string|bool|array<string, string>>
     */
    private function getRegistrationData(bool $isGuest): array
    {
        $data = [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'email' => 'max.mustermann@example.com',
            'emailConfirmation' => 'max.mustermann@example.com',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'storefrontUrl' => 'http://localhost',

            'billingAddress' => [
                'countryId' => $this->getValidCountryId(),
                'street' => 'Musterstrasse 13',
                'zipcode' => '48599',
                'city' => 'Epe',
            ],
        ];

        if (!$isGuest) {
            $data['createCustomerAccount'] = true;
            $data['password'] = TestDefaults::HASHED_PASSWORD;
        }

        return $data;
    }
}
