<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\ORM\DefinitionRegistry;
use Shopware\Core\Framework\ORM\Exception\MappingEntityRepositoryException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Client;

class VersionTest extends ApiTestCase
{
    /**
     * @var Client
     */
    private $unauthorizedClient;

    protected function setUp()
    {
        parent::setUp();

        $this->unauthorizedClient = $this->getClient();
        $this->unauthorizedClient->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
            'HTTP_X_SW_TENANT_ID' => Defaults::TENANT_ID,
        ]);
    }

    public function unprotectedRoutesDataProvider()
    {
        return [
            ['GET', '/api/v1/info'],
            ['GET', '/api/v1/entity-schema.json'],
        ];
    }

    public function protectedRoutesDataProvider()
    {
        return [
            ['GET', '/api/v1/product'],
            ['GET', '/api/v1/tax'],
            ['POST', '/api/sync'],
        ];
    }

    /**
     * @dataProvider unprotectedRoutesDataProvider
     */
    public function testNonVersionRoutesAreUnprotected(string $method, string $url): void
    {
        $this->unauthorizedClient->request($method, $url);
        $this->assertNotEquals(
            Response::HTTP_UNAUTHORIZED,
            $this->unauthorizedClient->getResponse()->getStatusCode(),
            'Route should not be protected. (URL: ' . $url . ')'
        );
    }

    public function testAuthShouldNotBeProtected(): void
    {
        $this->unauthorizedClient->request('POST', '/api/v1/auth');
        $this->assertEquals(
            Response::HTTP_UNAUTHORIZED,
            $this->unauthorizedClient->getResponse()->getStatusCode(),
            'Route should be protected. (URL: /api/v1/auth)'
        );

        $response = json_decode($this->unauthorizedClient->getResponse()->getContent(), true);

        $this->assertEquals('Invalid username and/or password.', $response['errors'][0]['detail']);
    }

    /**
     * @dataProvider protectedRoutesDataProvider
     */
    public function testVersionRoutesAreProtected(string $method, string $url): void
    {
        $this->unauthorizedClient->request($method, $url);
        $this->assertEquals(
            Response::HTTP_UNAUTHORIZED,
            $this->unauthorizedClient->getResponse()->getStatusCode(),
            'Route should be protected. (URL: ' . $url . ')'
        );
    }

    public function testContainerAlias(): void
    {
        $registry = self::$container->get(DefinitionRegistry::class);

        foreach ($registry->getElements() as $definition) {
            try {
                $repositoryClass = $definition::getRepositoryClass();
            } catch (MappingEntityRepositoryException $ex) {
                return;
            }

            $alias = self::$container->get($repositoryClass);

            try {
                $real = self::$container->get($repositoryClass . '.v' . PlatformRequest::API_VERSION);
            } catch (ServiceNotFoundException $ex) {
                $this->fail(sprintf(
                    'Repository service definition for api version "%d" is missing. (%s)',
                    PlatformRequest::API_VERSION,
                    $repositoryClass . '.v' . PlatformRequest::API_VERSION
                ));
            }

            $this->assertSame($alias, $real, sprintf('Repository version mismatch for "%s".', $definition::getEntityName()));
        }
    }
}
