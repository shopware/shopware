<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\SystemCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\SystemCheck\AppSystemCheck;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Symfony\Component\Clock\MockClock;

/**
 * The per-app rules are covered by the integration tests in tests/integration/Core/Framework/App/SystemCheck.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppSystemCheck::class)]
class AppSystemCheckTest extends TestCase
{
    public function testWithoutAppsEverythingIsOk(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);
        $connection->method('fetchAllAssociativeIndexed')->willReturn([]);

        $result = (new AppSystemCheck($connection, new MockClock()))->run();

        static::assertSame(Status::OK, $result->status);
        static::assertTrue($result->healthy);
        static::assertSame(['apps' => []], $result->extra);
    }

    public function testItIsAQuickAppCheck(): void
    {
        $check = new AppSystemCheck(static::createStub(Connection::class), new MockClock());

        static::assertSame('AppSystem', $check->name());
        static::assertSame(Category::EXTERNAL, $check->category());
        static::assertSame(['apps'], $check->tags());
        static::assertTrue($check->allowedToRunIn(SystemCheckExecutionContext::WEB));
        static::assertFalse($check->allowedToRunIn(SystemCheckExecutionContext::PRE_ROLLOUT));
    }
}
