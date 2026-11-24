<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\StoredResult;
use Shopware\Core\Framework\SystemCheck\SystemCheckResultStorage;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
class SystemCheckResultStorageTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private MockClock $clock;

    private SystemCheckResultStorage $storage;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM system_check_result');

        $this->clock = new MockClock('2026-11-24 10:00:00', 'UTC');
        $this->storage = new SystemCheckResultStorage($this->connection, $this->clock);
    }

    public function testSavesOneRowPerCheck(): void
    {
        $this->storage->save([
            new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true, ['statusCode' => 204, 'responseTime' => 0.5]),
            new Result('LocalesReadiness', Status::WARNING, 'Some locales are invalid', null),
        ]);

        static::assertSame([
            [
                'name' => 'AppUrlVerification',
                'status' => 'OK',
                'healthy' => 1,
                'message' => 'APP_URL points to this shop',
                'extra' => ['statusCode' => 204, 'responseTime' => 0.5],
                'checked_at' => '2026-11-24 10:00:00.000',
            ],
            [
                'name' => 'LocalesReadiness',
                'status' => 'WARNING',
                'healthy' => null,
                'message' => 'Some locales are invalid',
                'extra' => [],
                'checked_at' => '2026-11-24 10:00:00.000',
            ],
        ], $this->rows());
    }

    public function testALaterRunOverwritesTheStoredResult(): void
    {
        $this->storage->save([new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true)]);

        $this->clock->modify('+1 hour');
        $this->storage->save([new Result('AppUrlVerification', Status::ERROR, 'APP_URL does not point to this shop', false)]);

        $rows = $this->rows();

        static::assertCount(1, $rows);
        static::assertSame('ERROR', $rows[0]['status']);
        static::assertSame('2026-11-24 11:00:00.000', $rows[0]['checked_at']);
    }

    public function testReadsBackTheStoredResults(): void
    {
        $this->storage->save([
            new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true, ['statusCode' => 204]),
            new Result('LocalesReadiness', Status::SKIPPED, 'Check is not run due to previous failed checks.'),
        ]);

        $checkedAt = new \DateTimeImmutable('2026-11-24 10:00:00', new \DateTimeZone('UTC'));

        static::assertEquals([
            'AppUrlVerification' => new StoredResult(new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true, ['statusCode' => 204]), $checkedAt),
            'LocalesReadiness' => new StoredResult(new Result('LocalesReadiness', Status::SKIPPED, 'Check is not run due to previous failed checks.'), $checkedAt),
        ], $this->storage->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT name, status, healthy, message, extra, checked_at FROM system_check_result ORDER BY name');

        return array_map(static fn (array $row) => [
            ...$row,
            'healthy' => $row['healthy'] === null ? null : (int) $row['healthy'],
            'extra' => json_decode((string) $row['extra'], true, flags: \JSON_THROW_ON_ERROR),
        ], $rows);
    }
}
