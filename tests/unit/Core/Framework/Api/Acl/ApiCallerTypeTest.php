<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Acl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\ApiCallerType;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApiCallerType::class)]
class ApiCallerTypeTest extends TestCase
{
    public function testAUserIsIdentifiedByItsUserId(): void
    {
        $source = new AdminApiSource('user-id');

        static::assertSame(ApiCallerType::User, ApiCallerType::of($source));
        static::assertSame('user-id', ApiCallerType::User->idOf($source));
    }

    public function testAnIntegrationIsIdentifiedByItsIntegrationId(): void
    {
        $source = new AdminApiSource(null, 'integration-id');

        static::assertSame(ApiCallerType::Integration, ApiCallerType::of($source));
        static::assertSame('integration-id', ApiCallerType::Integration->idOf($source));
    }

    public function testAnIntegrationNamingAUserIsStillTheIntegration(): void
    {
        $source = new AdminApiSource(Uuid::randomHex(), 'integration-id');

        static::assertSame(ApiCallerType::Integration, ApiCallerType::of($source));
        static::assertSame('integration-id', ApiCallerType::Integration->idOf($source));
    }

    public function testASourceOutsideTheAdminApiIsNoCaller(): void
    {
        static::assertNull(ApiCallerType::of(new SystemSource()));
        static::assertNull(ApiCallerType::User->idOf(new SystemSource()));
    }
}
