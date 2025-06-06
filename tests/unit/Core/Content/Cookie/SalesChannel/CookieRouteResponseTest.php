<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRouteResponse;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieRouteResponse::class)]
class CookieRouteResponseTest extends TestCase
{
    public function testGetCookieGroups(): void
    {
        $collection = new CookieGroupCollection();
        $response = new CookieRouteResponse($collection);

        static::assertSame($collection, $response->getCookieGroups());
        static::assertSame($collection, $response->getObject());
    }
}
