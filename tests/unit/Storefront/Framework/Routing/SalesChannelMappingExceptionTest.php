<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Routing\Exception\SalesChannelMappingException;

/**
 * @internal
 */
#[CoversClass(SalesChannelMappingException::class)]
class SalesChannelMappingExceptionTest extends TestCase
{
    public function testException(): void
    {
        $exception = new SalesChannelMappingException('test');
        static::assertEquals('Unable to find a matching sales channel for the request: "test". Please make sure the domain mapping is correct.', $exception->getMessage());
        static::assertEquals('FRAMEWORK__INVALID_SALES_CHANNEL_MAPPING', $exception->getErrorCode());
        static::assertEquals(404, $exception->getStatusCode());
    }
}
