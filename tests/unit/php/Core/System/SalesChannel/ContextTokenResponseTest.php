<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\SalesChannel\ContextTokenResponse
 */
class ContextTokenResponseTest extends TestCase
{
    public function testGetTokenFromResponseBody(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $token = 'sw-token-value';
        $response = new ContextTokenResponse($token);
        static::assertSame($token, $response->getToken());
    }

    public function testGetTokenFromHeader(): void
    {
        Feature::skipTestIfInActive('v6.6.0.0', $this);

        $token = 'sw-token-value';
        $response = new ContextTokenResponse($token);
        static::assertSame($token, $response->getToken());

        // It should be stored in a header instead
        static::assertSame($token, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }
}
