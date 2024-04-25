<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Route;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Route\ApiRouteLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ApiRouteLoader::class)]
class ApiRouteLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $definitionRegistry = new StaticDefinitionInstanceRegistry(
            [new ProductDefinition()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGateway::class),
        );

        $loader = new ApiRouteLoader($definitionRegistry);

        static::assertTrue($loader->supports('resource', 'api'));

        $routes = $loader->load('resource');

        static::assertCount(7, $routes);
        static::assertArrayHasKey('api.product.detail', $routes->all());
        static::assertArrayHasKey('api.product.update', $routes->all());
        static::assertArrayHasKey('api.product.delete', $routes->all());
        static::assertArrayHasKey('api.product.list', $routes->all());
        static::assertArrayHasKey('api.product.search', $routes->all());
        static::assertArrayHasKey('api.product.search-ids', $routes->all());
        static::assertArrayHasKey('api.product.create', $routes->all());

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Do not add the "api" loader twice');
        $loader->load('resource', 'api');
    }
}
