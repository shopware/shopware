<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Oidc;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\Oidc\OidcClaims;

/**
 * @internal
 */
#[CoversClass(OidcClaims::class)]
class OidcClaimsTest extends TestCase
{
    public function testExposesTheResolvedClaims(): void
    {
        $claims = new OidcClaims(
            sub: 'user-123',
            email: 'jane@example.com',
            emailVerified: true,
            name: 'Jane Doe',
            preferredUsername: 'jane',
        );

        static::assertSame('user-123', $claims->sub);
        static::assertSame('jane@example.com', $claims->email);
        static::assertTrue($claims->emailVerified);
        static::assertSame('Jane Doe', $claims->name);
        static::assertSame('jane', $claims->preferredUsername);
    }

    public function testGetClaimReadsTheRawPayload(): void
    {
        $claims = new OidcClaims(
            sub: 'user-123',
            email: null,
            emailVerified: false,
            name: null,
            preferredUsername: null,
            claims: [
                'sub' => 'user-123',
                'groups' => ['idp-admins', 'idp-catalog'],
                'department' => 'ecommerce',
            ],
        );

        static::assertSame(['idp-admins', 'idp-catalog'], $claims->getClaim('groups'));
        static::assertSame('ecommerce', $claims->getClaim('department'));
        static::assertNull($claims->getClaim('missing'));
    }
}
