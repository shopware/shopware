<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\CriteriaQueryParameterProvider;

/**
 * @internal
 */
#[CoversClass(CriteriaQueryParameterProvider::class)]
class CriteriaQueryParameterProviderTest extends TestCase
{
    public function testGetParameters(): void
    {
        $provider = new CriteriaQueryParameterProvider();
        $parameters = $provider->getParameters();

        static::assertNotEmpty($parameters);
        static::assertIsArray($parameters);

        static::assertCount(14, $parameters);

        $parameters = array_column($parameters, null, 'name');

        // we assert only some of the parameters (to check general logic)
        static::assertArrayHasKey('page', $parameters);
        $page = $parameters['page'];
        static::assertSame('query', $page['in']);
        static::assertSame('integer', $page['schema']['type']);

        static::assertArrayHasKey('filter', $parameters);
        $filter = $parameters['filter'];
        static::assertSame('query', $filter['in']);
        static::assertSame('array', $filter['schema']['type']);
        static::assertSame('deepObject', $filter['style']);
        static::assertTrue($filter['explode']);

        static::assertArrayHasKey('associations', $parameters);
        $associations = $parameters['associations'];
        static::assertSame('query', $associations['in']);
        static::assertSame('#/components/schemas/Associations', $associations['schema']['$ref']);
        static::assertSame('deepObject', $associations['style']);
        static::assertTrue($associations['explode']);

        static::assertArrayHasKey('includes', $parameters);
        $includes = $parameters['includes'];
        static::assertSame('query', $includes['in']);
        static::assertSame('#/components/schemas/Includes', $includes['schema']['$ref']);
        static::assertSame('deepObject', $includes['style']);
        static::assertTrue($includes['explode']);
    }
}
