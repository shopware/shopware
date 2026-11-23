<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\ShopwareAuthorizationRequest;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwareAuthorizationRequest::class)]
class ShopwareAuthorizationRequestTest extends TestCase
{
    public function testResourceIsEmptyUntilSet(): void
    {
        $request = new ShopwareAuthorizationRequest();
        static::assertNull($request->getResource());

        $request->setResource('https://shop.example/api/_mcp');
        static::assertSame('https://shop.example/api/_mcp', $request->getResource());

        $request->setResource(null);
        static::assertNull($request->getResource());
    }
}
