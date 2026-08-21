<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ContextHandoffControllerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SessionTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private KernelBrowser $storefront;

    protected function setUp(): void
    {
        $this->storefront = KernelLifecycleManager::createBrowser($this->getKernel());
        $this->storefront->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
    }

    public function testGenerateReturnsHandoffTokenAndForbidsCaching(): void
    {
        $this->requestStorefront('POST', '/context/handoff/generate');

        $response = $this->storefront->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);
        static::assertArrayHasKey('token', $content);
        static::assertArrayHasKey('expiresAt', $content);
        static::assertIsString($content['token']);

        $sessionToken = $this->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($sessionToken);
        static::assertStringNotContainsString($sessionToken, $content['token']);
    }

    public function testRedeemWritesTheResolvedContextTokenIntoTheSession(): void
    {
        // establish the storefront session and remember the token it starts out with
        $this->requestStorefront('POST', '/context/handoff/generate');
        $sessionTokenBefore = $this->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($sessionTokenBefore);

        $storeApi = $this->createStoreApiBrowser();
        $storeApiContextToken = $storeApi->getServerParameter('HTTP_SW_CONTEXT_TOKEN');
        static::assertIsString($storeApiContextToken);
        static::assertNotSame($sessionTokenBefore, $storeApiContextToken);

        $handoffToken = $this->generateHandoffTokenOnStoreApi($storeApi);

        $this->requestStorefront('POST', '/context/handoff/redeem', ['token' => $handoffToken]);

        $response = $this->storefront->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));

        static::assertSame($storeApiContextToken, $this->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testRedeemAlsoWritesTheSalesChannelSuffixedSessionKey(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', true);

        $this->requestStorefront('POST', '/context/handoff/generate');

        $storeApi = $this->createStoreApiBrowser();
        $storeApiContextToken = $storeApi->getServerParameter('HTTP_SW_CONTEXT_TOKEN');
        static::assertIsString($storeApiContextToken);

        $this->requestStorefront('POST', '/context/handoff/redeem', [
            'token' => $this->generateHandoffTokenOnStoreApi($storeApi),
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $this->storefront->getResponse()->getStatusCode());

        $session = $this->getSession();
        $suffixedKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $this->getSalesChannelId();
        static::assertSame($storeApiContextToken, $session->get($suffixedKey));
        static::assertSame($storeApiContextToken, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testTokenGeneratedInTheStorefrontIsRedeemableOnTheStoreApi(): void
    {
        $this->requestStorefront('POST', '/context/handoff/generate');

        $response = $this->storefront->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);
        static::assertIsString($content['token'] ?? null);

        $sessionToken = $this->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($sessionToken);

        $storeApi = $this->createStoreApiBrowser(withContextToken: false);
        $storeApi->request('POST', '/store-api/context/handoff/redeem', ['token' => $content['token']]);

        static::assertSame(Response::HTTP_OK, $storeApi->getResponse()->getStatusCode(), (string) $storeApi->getResponse()->getContent());
        static::assertSame($sessionToken, $storeApi->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testRedeemIsRejectedForAGarbageToken(): void
    {
        $this->requestStorefront('POST', '/context/handoff/generate');
        $sessionTokenBefore = $this->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        $this->requestStorefront('POST', '/context/handoff/redeem', ['token' => 'not-a-jwt-at-all']);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->storefront->getResponse()->getStatusCode());
        static::assertSame($sessionTokenBefore, $this->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function requestStorefront(string $method, string $path, array $parameters = []): void
    {
        $this->storefront->request($method, EnvironmentHelper::getVariable('APP_URL') . $path, $parameters);
    }

    private function createStoreApiBrowser(bool $withContextToken = true): KernelBrowser
    {
        $accessKey = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT access_key FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($this->getSalesChannelId())]
        );
        static::assertIsString($accessKey);

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $parameters = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_SW_ACCESS_KEY' => $accessKey,
        ];

        if ($withContextToken) {
            $parameters['HTTP_SW_CONTEXT_TOKEN'] = Uuid::randomHex();
        }

        $browser->setServerParameters($parameters);

        return $browser;
    }

    private function generateHandoffTokenOnStoreApi(KernelBrowser $storeApi): string
    {
        $storeApi->request('POST', '/store-api/context/handoff/generate');

        $response = $storeApi->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);
        static::assertIsString($content['token'] ?? null);

        return $content['token'];
    }
}
