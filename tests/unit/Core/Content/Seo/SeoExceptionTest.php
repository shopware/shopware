<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Seo\SeoException
 */
#[Package('buyers-experience')]
class SeoExceptionTest extends TestCase
{
    public function testInvalidSalesChannelId(): void
    {
        $salesChannelId = 'invalid-sales-channel-id';

        $exception = SeoException::invalidSalesChannelId($salesChannelId);

        static::assertInstanceOf(InvalidSalesChannelIdException::class, $exception);
        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testSalesChannelIdParameterIsMissing(): void
    {
        $exception = SeoException::salesChannelIdParameterIsMissing();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(SeoException::SALES_CHANNEL_ID_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertEquals('Parameter "salesChannelId" is missing.', $exception->getMessage());
    }

    public function testTemplateParameterIsMissing(): void
    {
        $exception = SeoException::templateParameterIsMissing();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(SeoException::TEMPLATE_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertEquals('Parameter "template" is missing.', $exception->getMessage());
    }

    public function testEntityNameParameterIsMissing(): void
    {
        $exception = SeoException::entityNameParameterIsMissing();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(SeoException::ENTITY_NAME_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertEquals('Parameter "entityName" is missing.', $exception->getMessage());
    }

    public function testRouteNameParameterIsMissing(): void
    {
        $exception = SeoException::routeNameParameterIsMissing();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(SeoException::ROUTE_NAME_PARAMETER_IS_MISSING, $exception->getErrorCode());
        static::assertEquals('Parameter "routeName" is missing.', $exception->getMessage());
    }

    public function testSalesChannelNotFound(): void
    {
        $salesChannelId = 'not-found-sales-channel-id';

        $exception = SeoException::salesChannelNotFound($salesChannelId);

        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertEquals(SeoException::SALES_CHANNEL_NOT_FOUND, $exception->getErrorCode());
        static::assertEquals('Sales channel with id "not-found-sales-channel-id" not found.', $exception->getMessage());
        static::assertEquals(['salesChannelId' => $salesChannelId], $exception->getParameters());
    }
}
