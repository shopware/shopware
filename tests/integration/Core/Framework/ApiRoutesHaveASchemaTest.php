<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\System\CustomEntity\Api\CustomEntityApiController;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Shopware\Tests\Integration\Core\Framework\fixtures\QueryParameterAllowList;
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

    protected function setUp(): void
    {
        // Boot kernel, as some test definitions might still be registered in the old kernel
        KernelLifecycleManager::bootKernel();

        $connection = $this->getContainer()->get(Connection::class);
        if ($connection->getTransactionNestingLevel() === 0) {
            // transaction was implicitly closed on kernel boot, start it again to don't mess up test execution
            $connection->beginTransaction();
        }

        $router = $this->getContainer()->get(RouterInterface::class);
        $this->routes = $router->getRouteCollection();
    }

    public function testStoreApiRoutesHaveASchema(): void
    {
        $generator = $this->getContainer()->get(StoreApiGenerator::class);
        $schema = $generator->generate(
            $this->getContainer()->get(SalesChannelDefinitionInstanceRegistry::class)->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );

        $allowedQueryParams = $this->buildAllowedQueryParams();

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
                $this->checkQueryParameters($route, $schemaRoutes[$path], $allowedQueryParams, $schema);
                unset($schemaRoutes[$path]);

                continue;
            }
            if ($this->isRepositoryCrudRoute($route)) {
                $listPath = str_replace('{path}', '', $path);
                $crudPath = str_replace('{path}', '{id}', $path);
                unset($schemaRoutes[$listPath], $schemaRoutes[$crudPath]);

                continue;
            }

            $missingRoutes[] = $path;
        }

        if (!empty($schemaRoutes)) {
            foreach ($schemaRoutes as $path => $routeSchema) {
                $routesFromPathParameter = $this->getRoutesFromSchemaDefinitionPath($path, $routeSchema);
                foreach ($routesFromPathParameter as $routeFromPathParameter) {
                    if (\in_array($routeFromPathParameter, $missingRoutes, true)) {
                        unset($schemaRoutes[$path], $missingRoutes[array_search($routeFromPathParameter, $missingRoutes, true)]);
                    }
                }
                $missingRoutes = array_values($missingRoutes);
            }
        }

        static::assertSame([], array_keys($schemaRoutes), 'The schema contains routes that do not exist');
        // Add missing routes under:
        // src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/paths
        static::assertSame([
            '/_info/open-api-schema.json',
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
        $generator = $this->getContainer()->get(OpenApi3Generator::class);
        $schema = $generator->generate(
            $this->getContainer()->get(DefinitionInstanceRegistry::class)->getDefinitions(),
            DefinitionService::API
        );

        $schemaRoutes = $schema['paths'];
        $missingRoutes = [];

        foreach ($this->routes as $route) {
            $path = $route->getPath();
            $subPath = \substr($path, \strlen('/api'));
            if (!$this->isAdminApi($path)) {
                continue;
            }
            if (\array_key_exists($subPath, $schemaRoutes)) {
                $this->checkExperimentalState($route, $schemaRoutes[$subPath]);
                unset($schemaRoutes[$subPath]);

                continue;
            }
            if ($this->isRepositoryCrudRoute($route)) {
                $listPath = str_replace('{path}', '', $subPath);
                $crudPath = str_replace('{path}', '{id}', $subPath);
                unset($schemaRoutes[$listPath]);
                unset($schemaRoutes[$crudPath]);

                continue;
            }

            // Don't enforce schema for non-core routes (test can run on custom installations)
            if (!$this->isCoreRoute($route)) {
                continue;
            }

            $missingRoutes[] = $subPath;
        }
        sort($missingRoutes);

        static::assertSame([], array_keys($schemaRoutes), 'The schema contains routes that do not exist');
        // Add missing routes under:
        // src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths
        $this->assertJsonSnapshot(
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

        return $controllerClass === ApiController::class || $controllerClass === CustomEntityApiController::class;
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
                $operation['description'],
                \sprintf('Route "%s" is experimental but not documented as such in the schema, please add that note to the description.', $route->getPath())
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
     * @param array<string, array<string, true>> $allowedQueryParams
     * @param array<string, mixed> $fullSchema
     */
    private function checkQueryParameters(Route $route, array $schema, array $allowedQueryParams, array $fullSchema): void
    {
        $allowedForRoute = $allowedQueryParams[$route->getPath()] ?? [];

        foreach ($schema as $operation) {
            $parameters = $operation['parameters'] ?? [];
            $resolvedParameters = [];
            foreach ($parameters as $parameter) {
                if (isset($parameter['$ref'])) {
                    $resolvedParameters[] = $this->resolveRef($parameter['$ref'], $fullSchema);
                } else {
                    $resolvedParameters[] = $parameter;
                }
            }

            foreach ($resolvedParameters as $item) {
                if ($item['in'] !== 'query') {
                    continue;
                }

                $parameterName = $item['name'];

                // It's explicitly allowed
                if (isset($allowedForRoute[$parameterName])) {
                    continue;
                }

                // string params are fine
                if (isset($item['schema']['type']) && $item['schema']['type'] === 'string') {
                    continue;
                }

                static::fail(
                    \sprintf('Route "%s" has a non-string query parameter "%s" which is not allowed. Please add it to the allowed list in ApiRoutesHaveASchemaTest.', $route->getPath(), $parameterName)
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    private function getRoutesFromSchemaDefinitionPath(string $path, array $schema): array
    {
        $paths = [];
        foreach ($schema as $operation) {
            foreach ($operation['parameters'] ?? [] as $item) {
                if ($item['in'] !== 'path') {
                    continue;
                }

                if ($item['schema']['type'] === 'string' && !empty($item['schema']['enum'])) {
                    foreach ($item['schema']['enum'] as $enum) {
                        $paths[] = str_replace('{' . $item['name'] . '}', $enum, $path);
                    }
                }
            }
        }

        return $paths;
    }

    /**
     * Build the complete list of allowed query parameters by combining static params with params from the routes.
     *
     * @return array<string, array<string, true>>
     */
    private function buildAllowedQueryParams(): array
    {
        $allowList = QueryParameterAllowList::getQueryParameterAllowList();

        $groups = $allowList['groups'];
        $routes = $allowList['allowedList'];

        $allowedQueryParams = [];
        foreach ($routes as $route => $params) {
            $allowed = [];
            foreach ($params as $param) {
                if (str_starts_with($param, '@')) {
                    $groupParams = $groups[substr($param, 1)];
                    foreach ($groupParams as $p) {
                        $allowed[$p] = true;
                    }
                } else {
                    $allowed[$param] = true;
                }
            }
            $allowedQueryParams[$route] = $allowed;
        }

        return $allowedQueryParams;
    }

    /**
     * @param array<string, mixed> $fullSchema
     *
     * @return array<string, mixed>
     */
    private function resolveRef(string $ref, array $fullSchema): array
    {
        $refPath = \str_replace('#/', '', $ref);
        $parts = \explode('/', $refPath);

        $current = $fullSchema;
        foreach ($parts as $part) {
            if (!\is_array($current) || !\array_key_exists($part, $current)) {
                static::fail(\sprintf('Reference "%s" could not be resolved.', $ref));
            }

            $current = $current[$part];
        }

        return $current;
    }
}
