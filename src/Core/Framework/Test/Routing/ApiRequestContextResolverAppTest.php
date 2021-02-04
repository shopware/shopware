<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\App\StorefrontPluginRegistryTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ApiRequestContextResolverAppTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;
    use StorefrontPluginRegistryTestBehaviour;

    public function testCanReadWithPermission(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../App/Manifest/_fixtures/test');

        $browser = $this->createClient();
        $this->authorizeBrowserWithIntegrationByAppName($this->getBrowser(), 'test');

        $browser->request('GET', '/api/product');
        $response = $browser->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());
    }

    public function testCantReadWithoutPermission(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../App/Manifest/_fixtures/test');

        $browser = $this->createClient();
        $this->authorizeBrowserWithIntegrationByAppName($browser, 'test');

        $browser->request('GET', '/api/media');

        static::assertEquals(403, $browser->getResponse()->getStatusCode());
    }

    public function testCantReadWithoutAnyPermission(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../App/Manifest/_fixtures/minimal');

        $browser = $this->createClient();
        $this->authorizeBrowserWithIntegrationByAppName($browser, 'minimal');

        $browser->request('GET', '/api/product');

        static::assertEquals(403, $browser->getResponse()->getStatusCode());
    }

    public function testCanNotWriteWithoutPermissions(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->loadAppsFromDir(__DIR__ . '/../App/Manifest/_fixtures/minimal');

        $browser = $this->createClient();
        $this->authorizeBrowserWithIntegrationByAppName($browser, 'minimal');

        $browser->request(
            'POST',
            '/api/product',
            [],
            [],
            [],
            json_encode($this->getProductData($productId, $context))
        );
        $response = $browser->getResponse();

        static::assertEquals(403, $response->getStatusCode(), $response->getContent());
        $data = json_decode($response->getContent(), true);
        static::assertEquals(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, $data['errors'][0]['code']);
    }

    public function testCanWriteWithPermissionsSet(): void
    {
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->loadAppsFromDir(__DIR__ . '/../App/Manifest/_fixtures/test');

        $browser = $this->createClient();
        $this->authorizeBrowserWithIntegrationByAppName($browser, 'test');

        $browser->request(
            'POST',
            '/api/product',
            [],
            [],
            [],
            json_encode($this->getProductData($productId, $context))
        );

        static::assertEquals(204, $browser->getResponse()->getStatusCode());

        $product = $productRepository->search(new Criteria(), $context)->getEntities()->get($productId);

        static::assertNotNull($product);
    }

    public function testItCanUpdateAnExistingProduct(): void
    {
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productId = Uuid::randomHex();
        $newName = 'i got a new name';
        $context = Context::createDefaultContext();

        $productRepository->create([$this->getProductData($productId, $context)], $context);

        $this->loadAppsFromDir(__DIR__ . '/../App/Manifest/_fixtures/test');

        $browser = $this->createClient();
        $this->authorizeBrowserWithIntegrationByAppName($browser, 'test');

        $browser->request(
            'PATCH',
            '/api/product/' . $productId,
            [],
            [],
            [],
            json_encode([
                'name' => $newName,
            ])
        );

        static::assertEquals(204, $browser->getResponse()->getStatusCode());

        $product = $productRepository->search(new Criteria(), $context)->getEntities()->get($productId);

        static::assertNotNull($product);
        static::assertEquals($newName, $product->getName());
    }

    private function authorizeBrowserWithIntegrationByAppName(KernelBrowser $browser, string $appName): void
    {
        $app = $this->fetchApp($appName);
        if (!$app) {
            throw new \RuntimeException('No app found with name: ' . $appName);
        }

        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $secret = AccessKeyHelper::generateSecretAccessKey();

        $this->setAccessTokenForIntegration($app->getIntegrationId(), $accessKey, $secret);

        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secret,
        ];

        $browser->request('POST', '/api/oauth/token', $authPayload);

        $data = json_decode($browser->getResponse()->getContent(), true);

        if (!\array_key_exists('access_token', $data)) {
            throw new \RuntimeException(
                'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error' . print_r($data, true))
            );
        }

        $browser->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['access_token']));
    }

    private function getProductData(string $productId, Context $context)
    {
        return [
            'id' => $productId,
            'name' => 'created by integration',
            'productNumber' => 'SWC-1000',
            'stock' => 100,
            'manufacturer' => [
                'name' => 'app creator',
            ],
            'price' => [
                [
                    'gross' => 100,
                    'net' => 200,
                    'linked' => false,
                    'currencyId' => $context->getCurrencyId(),
                ],
            ],
            'tax' => [
                'name' => 'luxury',
                'taxRate' => '25',
            ],
        ];
    }

    private function fetchApp(string $appName): ?AppEntity
    {
        /** @var EntityRepositoryInterface $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));

        return $appRepository->search($criteria, Context::createDefaultContext())->first();
    }

    private function setAccessTokenForIntegration(string $integrationId, string $accessKey, string $secret): void
    {
        /** @var EntityRepositoryInterface $integrationRepository */
        $integrationRepository = $this->getContainer()->get('integration.repository');

        $integrationRepository->update([
            [
                'id' => $integrationId,
                'accessKey' => $accessKey,
                'secretAccessKey' => $secret,
            ],
        ], Context::createDefaultContext());
    }
}
