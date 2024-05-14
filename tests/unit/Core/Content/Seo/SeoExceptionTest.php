<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(SeoException::class)]
class SeoExceptionTest extends TestCase
{
    public function testInvalidSalesChannelId(): void
    {
        $salesChannelId = 'invalid-sales-channel-id';

        $exception = SeoException::invalidSalesChannelId($salesChannelId);

        static::assertInstanceOf(InvalidSalesChannelIdException::class, $exception);
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testSalesChannelIdParameterIsMissing(): void
    {
        $exception = SeoException::salesChannelIdParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::SALES_CHANNEL_ID_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "salesChannelId" is missing.', $exception->getMessage());
    }

    public function testTemplateParameterIsMissing(): void
    {
        $exception = SeoException::templateParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::TEMPLATE_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "template" is missing.', $exception->getMessage());
    }

    public function testEntityNameParameterIsMissing(): void
    {
        $exception = SeoException::entityNameParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::ENTITY_NAME_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "entityName" is missing.', $exception->getMessage());
    }

    public function testRouteNameParameterIsMissing(): void
    {
        $exception = SeoException::routeNameParameterIsMissing();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::ROUTE_NAME_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertSame('Parameter "routeName" is missing.', $exception->getMessage());
    }

    public function testSalesChannelNotFound(): void
    {
        $salesChannelId = 'not-found-sales-channel-id';

        $exception = SeoException::salesChannelNotFound($salesChannelId);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(SeoException::SALES_CHANNEL_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find sales channel with id "not-found-sales-channel-id"', $exception->getMessage());
        static::assertSame($salesChannelId, $exception->getParameters()['value']);
    }
}
