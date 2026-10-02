<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization\Ownership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\Ownership;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Ownership::class)]
class OwnershipTest extends TestCase
{
    public function testAWebhookWithAnAppBelongsToIt(): void
    {
        $ownership = new Ownership(Uuid::randomHex(), Uuid::randomHex(), null, null);

        static::assertTrue($ownership->belongsToApp());
    }

    public function testAWebhookWithoutAnAppDoesNotBelongToOne(): void
    {
        $ownership = new Ownership(Uuid::randomHex(), null, Uuid::randomHex(), null);

        static::assertFalse($ownership->belongsToApp());
    }

    public function testTheOwningUserOwnsIt(): void
    {
        $userId = Uuid::randomHex();
        $ownership = new Ownership(Uuid::randomHex(), null, $userId, null);

        static::assertTrue($ownership->isOwnedBy(new AdminApiSource($userId)));
    }

    public function testAnotherUserDoesNotOwnIt(): void
    {
        $ownership = new Ownership(Uuid::randomHex(), null, Uuid::randomHex(), null);

        static::assertFalse($ownership->isOwnedBy(new AdminApiSource(Uuid::randomHex())));
    }

    public function testTheOwningIntegrationOwnsIt(): void
    {
        $integrationId = Uuid::randomHex();
        $ownership = new Ownership(Uuid::randomHex(), null, null, $integrationId);

        static::assertTrue($ownership->isOwnedBy(new AdminApiSource(null, $integrationId)));
    }

    public function testAnotherIntegrationDoesNotOwnIt(): void
    {
        $ownership = new Ownership(Uuid::randomHex(), null, null, Uuid::randomHex());

        static::assertFalse($ownership->isOwnedBy(new AdminApiSource(null, Uuid::randomHex())));
    }

    public function testAWebhookWithoutAnOwnerIsOwnedByNobody(): void
    {
        $ownership = new Ownership(Uuid::randomHex(), null, null, null);

        static::assertFalse($ownership->isOwnedBy(new AdminApiSource(Uuid::randomHex())));
        static::assertFalse($ownership->isOwnedBy(new AdminApiSource(null, Uuid::randomHex())));
    }

    public function testARecordedUserIsCheckedEvenWhenTheSourceAlsoCarriesAnIntegration(): void
    {
        $userId = Uuid::randomHex();
        $ownership = new Ownership(Uuid::randomHex(), null, $userId, null);

        static::assertTrue($ownership->isOwnedBy(new AdminApiSource($userId, Uuid::randomHex())));
        static::assertFalse($ownership->isOwnedBy(new AdminApiSource(Uuid::randomHex(), Uuid::randomHex())));
    }
}
