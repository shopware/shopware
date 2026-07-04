<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\OAuth\Scope;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\OAuth\Scope\MfaPendingScope;

/**
 * @internal
 */
#[CoversClass(MfaPendingScope::class)]
class MfaPendingScopeTest extends TestCase
{
    public function testGetIdentifier(): void
    {
        static::assertSame('admin-mfa-pending', (new MfaPendingScope())->getIdentifier());
    }

    public function testJsonSerializeMatchesIdentifier(): void
    {
        $scope = new MfaPendingScope();

        static::assertSame('admin-mfa-pending', $scope->jsonSerialize());
        static::assertSame($scope->getIdentifier(), $scope->jsonSerialize());
        static::assertSame('"admin-mfa-pending"', json_encode($scope));
    }

    public function testCustomMarkerIdentifier(): void
    {
        $scope = new MfaPendingScope('admin-mfa-challenge:abc123');

        static::assertSame('admin-mfa-challenge:abc123', $scope->getIdentifier());
        static::assertSame('admin-mfa-challenge:abc123', $scope->jsonSerialize());
    }
}
