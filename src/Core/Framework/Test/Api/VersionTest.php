<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

class VersionTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var HttpKernelBrowser
     */
    private $unauthorizedClient;

    protected function setUp(): void
    {
        $this->unauthorizedClient = $this->getBrowser();
        $this->unauthorizedClient->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
        ]);
    }

    public function protectedRoutesDataProvider(): array
    {
        return [
            ['GET', '/api/v' . PlatformRequest::API_VERSION . '/product'],
            ['GET', '/api/v' . PlatformRequest::API_VERSION . '/tax'],
            ['POST', '/api/v' . PlatformRequest::API_VERSION . '/_action/sync'],
            ['GET', '/api/v' . PlatformRequest::API_VERSION . '/_info/swagger.html'],
            ['GET', '/api/v' . PlatformRequest::API_VERSION . '/_info/entity-schema.json'],
        ];
    }

    public function testAuthShouldNotBeProtected(): void
    {
        $this->unauthorizedClient->request('POST', '/api/oauth/token');
        static::assertEquals(
            Response::HTTP_BAD_REQUEST,
            $this->unauthorizedClient->getResponse()->getStatusCode(),
            'Route should be protected. (URL: /api/oauth/token)'
        );

        $response = json_decode($this->unauthorizedClient->getResponse()->getContent(), true);

        static::assertEquals('The authorization grant type is not supported by the authorization server.', $response['errors'][0]['title']);
        static::assertEquals('Check that all required parameters have been provided', $response['errors'][0]['detail']);
    }

    /**
     * @dataProvider protectedRoutesDataProvider
     */
    public function testRoutesAreProtected(string $method, string $url): void
    {
        $this->unauthorizedClient->request($method, $url);
        static::assertEquals(
            Response::HTTP_UNAUTHORIZED,
            $this->unauthorizedClient->getResponse()->getStatusCode(),
            'Route should be protected. (URL: ' . $url . ')'
        );
    }
}
