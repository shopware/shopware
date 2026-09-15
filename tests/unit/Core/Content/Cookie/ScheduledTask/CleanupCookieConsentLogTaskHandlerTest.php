<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupCookieConsentLogTaskHandler::class)]
class CleanupCookieConsentLogTaskHandlerTest extends TestCase
{
    public function testRunDeletesEverythingOlderThanTheRetention(): void
    {
        $storage = $this->createMock(AbstractCookieConsentLogStorage::class);
        $storage->expects($this->once())
            ->method('cleanup')
            ->with(static::callback(static fn (\DateTimeImmutable $before) => $before->format('Y-m-d H:i:s') === '2026-03-15 12:00:00'));

        $handler = new CleanupCookieConsentLogTaskHandler(
            static::createStub(EntityRepository::class),
            new NullLogger(),
            $storage,
            new MockClock('2026-07-13 12:00:00'),
            120,
        );

        $handler->run();
    }
}
