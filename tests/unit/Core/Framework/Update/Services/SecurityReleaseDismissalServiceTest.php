<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Services\SecurityReleaseDismissalService;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SecurityReleaseDismissalService::class)]
class SecurityReleaseDismissalServiceTest extends TestCase
{
    public function testIsDismissedByUserReturnsFalseWhenNoConfigExists(): void
    {
        $repository = StaticEntityRepository::of(UserConfigCollection::class, [new UserConfigCollection([])]);
        $service = new SecurityReleaseDismissalService($repository, new MockClock());

        $isDismissed = $service->isDismissedByUser('user-id', '6.2.0', Context::createDefaultContext());

        static::assertFalse($isDismissed);
    }

    public function testIsDismissedByUserReturnsFalseForDifferentVersion(): void
    {
        $config = $this->createUserConfigEntity('user-id', ['version' => '6.1.0', 'dismissedAt' => '2026-01-01T00:00:00+00:00']);
        $repository = StaticEntityRepository::of(UserConfigCollection::class, [new UserConfigCollection([$config])]);
        $service = new SecurityReleaseDismissalService($repository, new MockClock());

        $isDismissed = $service->isDismissedByUser('user-id', '6.2.0', Context::createDefaultContext());

        static::assertFalse($isDismissed);
    }

    public function testIsDismissedByUserReturnsTrueForSameVersion(): void
    {
        $config = $this->createUserConfigEntity('user-id', ['version' => '6.2.0', 'dismissedAt' => '2026-01-01T00:00:00+00:00']);
        $repository = StaticEntityRepository::of(UserConfigCollection::class, [new UserConfigCollection([$config])]);
        $service = new SecurityReleaseDismissalService($repository, new MockClock());

        $isDismissed = $service->isDismissedByUser('user-id', '6.2.0', Context::createDefaultContext());

        static::assertTrue($isDismissed);
    }

    public function testDismissUpsertsConfig(): void
    {
        $repository = StaticEntityRepository::of(UserConfigCollection::class, [['config-id']]);
        $clock = new MockClock('2026-10-13T12:00:00+00:00');
        $service = new SecurityReleaseDismissalService($repository, $clock);

        $service->dismiss('user-id', '6.2.0', Context::createDefaultContext());

        $payload = $repository->upserts[0][0];
        static::assertSame('user-id', $payload['userId']);
        static::assertSame('security_release.dismissed', $payload['key']);
        static::assertSame('6.2.0', $payload['value']['version']);
        static::assertSame('2026-10-13T12:00:00+00:00', $payload['value']['dismissedAt']);
    }

    private function createUserConfigEntity(string $userId, array $value): UserConfigEntity
    {
        $entity = new UserConfigEntity();
        $entity->setId('config-id');
        $entity->setUserId($userId);
        $entity->setKey(SecurityReleaseDismissalService::USER_CONFIG_KEY);
        $entity->setValue($value);
        $entity->setUniqueIdentifier('config-id');

        return $entity;
    }
}
