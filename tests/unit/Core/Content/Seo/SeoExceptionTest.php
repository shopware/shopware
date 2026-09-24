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
#[Package('inventory')]
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

    public function testAppSeoUrlPathInvalid(): void
    {
        $exception = SeoException::appSeoUrlPathInvalid('imprint', 'legal#notice');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::APP_SEO_URL_PATH_INVALID, $exception->getErrorCode());
        static::assertSame('The path "legal#notice" of the SEO URL "imprint" contains characters that are not allowed in URLs.', $exception->getMessage());
        static::assertSame(['path' => 'legal#notice', 'seoUrlName' => 'imprint'], $exception->getParameters());
    }

    public function testAppSeoUrlPathAlreadyRegistered(): void
    {
        $exception = SeoException::appSeoUrlPathAlreadyRegistered('imprint', 'legal-notice', 'SwagLegalApp');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::APP_SEO_URL_PATH_ALREADY_REGISTERED, $exception->getErrorCode());
        static::assertSame('The path "legal-notice" of the SEO URL "imprint" is already registered by app "SwagLegalApp".', $exception->getMessage());
        static::assertSame(['path' => 'legal-notice', 'seoUrlName' => 'imprint', 'owningApp' => 'SwagLegalApp'], $exception->getParameters());
    }

    public function testAppSeoUrlPathInUse(): void
    {
        $exception = SeoException::appSeoUrlPathInUse('login', 'account/login');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SeoException::APP_SEO_URL_PATH_IN_USE, $exception->getErrorCode());
        static::assertSame('The path "account/login" of the SEO URL "login" is already used by a storefront route or another SEO URL.', $exception->getMessage());
        static::assertSame(['path' => 'account/login', 'seoUrlName' => 'login'], $exception->getParameters());
    }
}
