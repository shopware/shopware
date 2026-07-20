<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\SystemCheck\Util\StorefrontHealthCheckResult;

/**
 * @internal
 */
#[CoversClass(StorefrontHealthCheckResult::class)]
class StorefrontHealthCheckResultTest extends TestCase
{
    public function testCreate(): void
    {
        $result = StorefrontHealthCheckResult::create('http://localhost:8000', 200, 0.123);

        static::assertSame('http://localhost:8000', $result->storefrontUrl);
        static::assertSame(200, $result->responseCode);
        static::assertSame(0.123, $result->responseTime);
    }
}
