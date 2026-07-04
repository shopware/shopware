<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallengeStore;
use Shopware\Core\Framework\AdminAuth\ScheduledTask\PruneMfaChallengesTask;
use Shopware\Core\Framework\AdminAuth\ScheduledTask\PruneMfaChallengesTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(PruneMfaChallengesTaskHandler::class)]
#[CoversClass(PruneMfaChallengesTask::class)]
class PruneMfaChallengesTaskHandlerTest extends TestCase
{
    public function testTaskDefinition(): void
    {
        static::assertSame('admin_auth.prune_mfa_challenges', PruneMfaChallengesTask::getTaskName());
        static::assertSame(3600, PruneMfaChallengesTask::getDefaultInterval());
    }

    public function testRunDeletesExpiredChallenges(): void
    {
        $store = $this->createMock(MfaChallengeStore::class);
        $store->expects($this->once())->method('deleteExpired');

        $this->createHandler($store)->run();
    }

    #[DisabledFeatures(['ADMIN_AUTH'])]
    public function testRunDoesNothingWhenFeatureIsInactive(): void
    {
        $store = $this->createMock(MfaChallengeStore::class);
        $store->expects($this->never())->method('deleteExpired');

        $this->createHandler($store)->run();
    }

    private function createHandler(MfaChallengeStore $store): PruneMfaChallengesTaskHandler
    {
        return new PruneMfaChallengesTaskHandler(
            $this->createMock(EntityRepository::class),
            new NullLogger(),
            $store
        );
    }
}
