<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\SystemCheck\SystemCheckResultStore;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
class SystemCheckResultStoreTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private MockClock $clock;

    private SystemCheckResultStore $store;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM system_check_result');

        $this->clock = new MockClock('2026-11-24 10:00:00', 'UTC');
        $this->store = new SystemCheckResultStore($connection, $this->clock, new NullLogger());
    }

    public function testReadsBackWhatWasSaved(): void
    {
        $this->store->save([
            new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true, ['statusCode' => 204, 'responseTime' => 0.5]),
            new Result('LocalesReadiness', Status::WARNING, 'Some locales are invalid', null),
        ], SystemCheckExecutionContext::CLI);

        $stored = $this->store->all();

        static::assertSame(['AppUrlVerification', 'LocalesReadiness'], array_keys($stored));
        static::assertEquals(
            new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true, ['statusCode' => 204, 'responseTime' => 0.5]),
            $stored['AppUrlVerification']->result
        );
        static::assertNull($stored['LocalesReadiness']->result->healthy);
        static::assertSame(SystemCheckExecutionContext::CLI, $stored['AppUrlVerification']->context);
        static::assertEquals(new \DateTimeImmutable('2026-11-24 10:00:00', new \DateTimeZone('UTC')), $stored['AppUrlVerification']->checkedAt);
    }

    public function testALaterRunOverwritesTheStoredResult(): void
    {
        $this->store->save([new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true)], SystemCheckExecutionContext::CLI);

        $this->clock->modify('+1 hour');
        $this->store->save([new Result('AppUrlVerification', Status::ERROR, 'APP_URL does not point to this shop', false)], SystemCheckExecutionContext::RECURRENT);

        $stored = $this->store->all();

        static::assertCount(1, $stored);
        static::assertSame(Status::ERROR, $stored['AppUrlVerification']->result->status);
        static::assertSame(SystemCheckExecutionContext::RECURRENT, $stored['AppUrlVerification']->context);
        static::assertEquals(new \DateTimeImmutable('2026-11-24 11:00:00', new \DateTimeZone('UTC')), $stored['AppUrlVerification']->checkedAt);
    }
}
