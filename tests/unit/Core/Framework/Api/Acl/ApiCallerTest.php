<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Acl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\ApiCaller;
use Shopware\Core\Framework\Api\Acl\IdentityType;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApiCaller::class)]
class ApiCallerTest extends TestCase
{
    public function testAUserIsIdentifiedByItsUserId(): void
    {
        $caller = ApiCaller::of(new AdminApiSource('user-id'));

        static::assertNotNull($caller);
        static::assertSame(IdentityType::User, $caller->type);
        static::assertSame('user-id', $caller->id);
    }

    public function testAnIntegrationIsIdentifiedByItsIntegrationId(): void
    {
        $caller = ApiCaller::of(new AdminApiSource(null, 'integration-id'));

        static::assertNotNull($caller);
        static::assertSame(IdentityType::Integration, $caller->type);
        static::assertSame('integration-id', $caller->id);
    }

    public function testAnIntegrationNamingAUserIsStillTheIntegration(): void
    {
        $caller = ApiCaller::of(new AdminApiSource(Uuid::randomHex(), 'integration-id'));

        static::assertNotNull($caller);
        static::assertSame(IdentityType::Integration, $caller->type);
        static::assertSame('integration-id', $caller->id);
    }

    public function testASourceWithoutAUserOrIntegrationIsNoCaller(): void
    {
        static::assertNull(ApiCaller::of(new AdminApiSource(null)));
    }

    public function testASourceOutsideTheAdminApiIsNoCaller(): void
    {
        static::assertNull(ApiCaller::of(new SystemSource()));
    }
}
