<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(ContextTokenResponse::class)]
class ContextTokenResponseTest extends TestCase
{
    public function testGetTokenFromResponseBody(): void
    {
        $token = 'sw-token-value';
        $response = new ContextTokenResponse($token);
        static::assertSame($token, $response->getToken());
    }

    public function testGetTokenFromHeader(): void
    {
        $token = 'sw-token-value';
        $response = new ContextTokenResponse($token);
        static::assertSame($token, $response->getToken());

        // It should be stored in a header instead
        static::assertSame($token, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }
}
