<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
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

    public function setUp(): void
    {
        $router = $this->getContainer()->get(RouterInterface::class);
        $this->routes = $router->getRouteCollection();
    }

    public function testStoreApiRoutesHaveASchema(): void
    {
        $generator = $this->getContainer()->get(StoreApiGenerator::class);
        $schema = $generator->generate(
            $this->getContainer()->get(SalesChannelDefinitionInstanceRegistry::class)->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TypeJsonApi
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
            '/_info/openapi3.json',
            '/_info/open-api-schema.json',
            '/_info/swagger.html',
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
        $generator = $this->getContainer()->get(OpenApi3Generator::class);
        $schema = $generator->generate(
            $this->getContainer()->get(DefinitionInstanceRegistry::class)->getDefinitions(),
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
}
