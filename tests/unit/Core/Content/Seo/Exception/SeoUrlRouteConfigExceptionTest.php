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
    public function testRouteConfigMissingParameterKeyForPrimaryKey(): void
    {
        $exception = SeoUrlRouteConfigException::routeConfigMissingParameterKeyForPrimaryKey('product');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoUrlRouteConfigException::ROUTE_CONFIG_MISSING_PARAMETER_KEY_FOR_PRIMARY_KEY, $exception->getErrorCode());
        static::assertSame('Missing parameter key for primary key in route config of entity "product".', $exception->getMessage());
        static::assertSame(['entityName' => 'product'], $exception->getParameters());
    }

    public function testRouteConfigNotFoundForEntityName(): void
    {
        $exception = SeoUrlRouteConfigException::routeConfigNotFoundForEntityName('product');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoUrlRouteConfigException::ROUTE_CONFIG_NOT_FOUND_FOR_ENTITY_NAME, $exception->getErrorCode());
        static::assertSame('No route config found for given entity name "product".', $exception->getMessage());
        static::assertSame(['entityName' => 'product'], $exception->getParameters());
    }
}
