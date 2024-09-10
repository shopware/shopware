<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class ApiRoutesHaveASchemaTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SnapshotTesting;

    private RouteCollection $routes;

    private ContainerInterface $container;

    protected function setUp(): void
    {
        $container = KernelLifecycleManager::bootKernel()->getContainer()->get('test.service_container');

        static::assertInstanceOf(ContainerInterface::class, $container);

        $this->container = $container;

        $router = $this->container->get(RouterInterface::class);
        $this->routes = $router->getRouteCollection();
    }

    public function testStoreApiRoutesHaveASchema(): void
    {
        $generator = $this->container->get(StoreApiGenerator::class);
        $schema = $generator->generate(
            $this->container->get(SalesChannelDefinitionInstanceRegistry::class)->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );

        $schemaRoutes = $schema['paths'];
        $missingRoutes = [];

        foreach ($this->routes as $route) {
            if (!$this->isCoreRoute($route)) {
                continue;
            }
            $path = $route->getPath();
            if (!$this->isStoreApi($path)) {
                continue;
            }
            $path = \substr($path, \strlen('/store-api'));
            if (\array_key_exists($path, $schemaRoutes)) {
                $this->checkExperimentalState($route, $schemaRoutes[$path]);
                $this->checkQueryParameters($route, $schemaRoutes[$path]);
                unset($schemaRoutes[$path]);

                continue;
            }
            if ($this->isRepositoryCrudRoute($route)) {
                $listPath = str_replace('{path}', '', $path);
                $crudPath = str_replace('{path}', '{id}', $path);
                unset($schemaRoutes[$listPath]);
                unset($schemaRoutes[$crudPath]);

                continue;
            }

            $missingRoutes[] = $path;
        }

        static::assertSame([], array_keys($schemaRoutes), 'The schema contains routes that do not exist');
        // Add missing routes under:
        // src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/paths
        static::assertSame([
            '/_info/open-api-schema.json',
            '/_info/swagger.html',
            '/_info/stoplightio.html',
            '/context',
            '/account/customer',
            '/account/address/{addressId}',
            '/checkout/cart/line-item',
            '/checkout/cart/line-item',
            '/checkout/cart',
        ], $missingRoutes, 'Routes are missing in the schema');
    }

    public function testAdminApiRoutesHaveASchema(): void
    {
        $generator = $this->container->get(OpenApi3Generator::class);
        $schema = $generator->generate(
            $this->container->get(DefinitionInstanceRegistry::class)->getDefinitions(),
            DefinitionService::API
        );

        $schemaRoutes = $schema['paths'];
        $missingRoutes = [];

        foreach ($this->routes as $route) {
            if (!$this->isCoreRoute($route)) {
                continue;
            }
            $path = $route->getPath();
            if (!$this->isAdminApi($path)) {
                continue;
            }
            $path = \substr($path, \strlen('/api'));
            if (\array_key_exists($path, $schemaRoutes)) {
                $this->checkExperimentalState($route, $schemaRoutes[$path]);
                unset($schemaRoutes[$path]);

                continue;
            }
            if ($this->isRepositoryCrudRoute($route)) {
                $listPath = str_replace('{path}', '', $path);
                $crudPath = str_replace('{path}', '{id}', $path);
                unset($schemaRoutes[$listPath]);
                unset($schemaRoutes[$crudPath]);

                continue;
            }

            $missingRoutes[] = $path;
        }
        sort($missingRoutes);

        static::assertSame([], array_keys($schemaRoutes), 'The schema contains routes that do not exist');
        // Add missing routes under:
        // src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths
        $this->assertSnapshot(
            'routes_without_schema',
            $missingRoutes,
            'Routes are missing in the schema'
        );
    }

    private function isStoreApi(string $path): bool
    {
        return str_starts_with($path, '/store-api');
    }

    private function isAdminApi(string $path): bool
    {
        return str_starts_with($path, '/api');
    }

    private function isRepositoryCrudRoute(Route $route): bool
    {
        $controllerClass = strtok($route->getDefault('_controller'), ':');

        return $controllerClass === ApiController::class;
    }

    private function isCoreRoute(Route $route): bool
    {
        $controllerClass = (string) strtok((string) $route->getDefault('_controller'), ':');

        return str_starts_with($controllerClass, 'Shopware\Core');
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function checkExperimentalState(Route $route, array $schema): void
    {
        if (!$this->isExperimentalRoute($route)) {
            return;
        }

        // schema has http methods as keys, we want to check all of them
        foreach ($schema as $operation) {
            static::assertContains('Experimental', $operation['tags'], \sprintf('Route "%s" is experimental but not tagged as such in the schema, please add the "Experimental" tag.', $route->getPath()));

            static::assertStringContainsString(
                'Experimental API, not part of our backwards compatibility promise, thus this API can introduce breaking changes at any time.',
                $operation['summary'],
                \sprintf('Route "%s" is experimental but not documented as such in the schema, please add that note to the summary.', $route->getPath())
            );
        }
    }

    private function isExperimentalRoute(Route $route): bool
    {
        /** @var class-string<object> $controllerClass */
        $controllerClass = (string) strtok((string) $route->getDefault('_controller'), ':');

        $method = (string) strtok(':');
        $reflection = new \ReflectionClass($controllerClass);

        if (str_contains($reflection->getDocComment() ?: '', '@experimental')) {
            return true;
        }

        try {
            $reflectionMethod = $reflection->getMethod($method);
        } catch (\ReflectionException) {
            return false;
        }

        return str_contains($reflectionMethod->getDocComment() ?: '', '@experimental');
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function checkQueryParameters(Route $route, array $schema): void
    {
        $whitelist = [
            '/store-api/category/{navigationId}:slots',
            '/store-api/shipping-method:onlyAvailable',
            '/store-api/checkout/cart/line-item:ids',
            '/store-api/_info/openapi3.json:type',
        ];

        foreach ($schema as $operation) {
            foreach ($operation['parameters'] ?? [] as $item) {
                if ($item['in'] !== 'query') {
                    continue;
                }

                /** @var string $parameterName */
                $parameterName = $item['name'];
                $key = $route->getPath() . ':' . $parameterName;

                static::assertContains($key, $whitelist, \sprintf('Route "%s" has a query parameter "%s" which is not allowed.', $route->getPath(), $parameterName));
            }
        }
    }
}
