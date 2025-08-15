<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\SalesChannel\CookieHashRouteResponse;
use Shopware\Core\Content\Cookie\Struct\CookieHash;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieHashRouteResponse::class)]
class CookieHashRouteResponseTest extends TestCase
{
    public function testConstructorAndGetter(): void
    {
        $hash = 'abc123def456';
        $response = new CookieHashRouteResponse($hash);

        static::assertSame($hash, $response->getCookieHash());
    }

    public function testResponseContainsCookieHashStruct(): void
    {
        $hash = 'test-hash-value';
        $response = new CookieHashRouteResponse($hash);

        $object = $response->getObject();
        static::assertInstanceOf(CookieHash::class, $object);
        static::assertSame($hash, $object->cookieHash);
        static::assertSame('cookie_hash', $object->getApiAlias());
    }
}
