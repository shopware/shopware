<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SeoUrlRouteConfigException::class)]
class SeoUrlRouteConfigExceptionTest extends TestCase
{
    public function testRouteParametersMismatching(): void
    {
        $exception = SeoUrlRouteConfigException::routeParametersMismatching(['productId'], ['abc123', 'extra']);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoUrlRouteConfigException::ROUTE_PARAMETERS_MISMATCHING, $exception->getErrorCode());
        static::assertSame('Mismatch between required route parameters and given values.', $exception->getMessage());
        static::assertSame(['required' => ['productId'], 'given' => ['abc123', 'extra']], $exception->getParameters());
    }

    public function testRouteConfigNotFoundForEntityName(): void
    {
        $exception = SeoUrlRouteConfigException::routeConfigNotFoundForEntityName('product');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoUrlRouteConfigException::ROUTE_CONFIG_NOT_FOUND_FOR_ENTITY_NAME, $exception->getErrorCode());
        static::assertSame('No route config found for given entity name.', $exception->getMessage());
        static::assertSame(['entityName' => 'product'], $exception->getParameters());
    }
}
