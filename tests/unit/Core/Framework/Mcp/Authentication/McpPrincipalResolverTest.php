<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Authentication\McpPrincipalResolver;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpPrincipalResolver::class)]
class McpPrincipalResolverTest extends TestCase
{
    private const USER_ID = '0190a1b2c3d4e5f6a7b8c9d0e1f2a3b4';
    private const INTEGRATION_KEY = 'SWIAINTEGRATIONKEY0000000000';
    private const USER_KEY = 'SWUAUSERKEY000000000000000000';

    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('requestProvider')]
    public function testResolvesThePrincipal(array $attributes, ?Context $context, ?string $expected): void
    {
        $request = new Request(attributes: $attributes);
        if ($context !== null) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
        }

        static::assertSame($expected, (new McpPrincipalResolver())->resolve($request));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, Context|null, string|null}>
     */
    public static function requestProvider(): iterable
    {
        yield 'user-bound token' => [
            [
                PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID => 'jti-1',
                PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID => 'administration',
                PlatformRequest::ATTRIBUTE_OAUTH_USER_ID => self::USER_ID,
            ],
            new Context(new AdminApiSource(self::USER_ID)),
            'user-' . self::USER_ID,
        ];

        yield 'integration token or access-key headers' => [
            [
                PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID => 'mcp-' . self::INTEGRATION_KEY,
                PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID => self::INTEGRATION_KEY,
            ],
            new Context(new AdminApiSource(null, 'integration-id')),
            'integration-' . self::INTEGRATION_KEY,
        ];

        yield 'integration acting for a user (sw-app-user-id)' => [
            [
                PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID => 'jti-2',
                PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID => self::INTEGRATION_KEY,
            ],
            new Context(new AdminApiSource(self::USER_ID, 'integration-id')),
            'integration-' . self::INTEGRATION_KEY . '-user-' . self::USER_ID,
        ];

        yield 'user access key' => [
            [
                PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID => 'jti-3',
                PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID => self::USER_KEY,
            ],
            new Context(new AdminApiSource(self::USER_ID)),
            'user-' . self::USER_ID,
        ];

        yield 'user access key without resolved user' => [
            [PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID => self::USER_KEY],
            new Context(new SystemSource()),
            null,
        ];

        yield 'sales-channel access key' => [
            [PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID => 'SWSCSALESCHANNELKEY000000000'],
            null,
            null,
        ];

        yield 'unknown client id' => [
            [PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID => 'administration'],
            null,
            null,
        ];

        yield 'unauthenticated' => [[], null, null];
    }
}
