<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\RetainedSecret;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\RetainedSecret\AppRetainedSecretGateway;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
class AppRetainedSecretGatewayTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private const APP_NAME = 'test';

    private Connection $connection;

    private MockClock $clock;

    private AppRetainedSecretGateway $gateway;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->clock = new MockClock('2026-01-01 12:00:00');
        $this->gateway = new AppRetainedSecretGateway($this->connection, $this->clock);

        $this->loadAppsFromDir((string) realpath(__DIR__ . '/../Manifest/_fixtures/test'));
        $this->setAppSecrets('committed-secret', ['pending-newest', 'pending-oldest']);
    }

    public function testRetainsEveryUnconfirmedSecretAndTheCommittedSecretForSeventyTwoHours(): void
    {
        $this->gateway->retainCandidates('old-shop');

        $retained = $this->gateway->findValid(self::APP_NAME);

        static::assertEqualsCanonicalizing(
            ['pending-newest', 'pending-oldest', 'committed-secret'],
            array_column($retained, 'secret')
        );
        foreach ($retained as $row) {
            static::assertSame('old-shop', $row['shopId']);
            static::assertSame('2026-01-04 12:00:00', $row['expiresAt']->format('Y-m-d H:i:s'));
        }
    }

    public function testRetainingAgainRefreshesTheExpiryWithoutDuplicatingRows(): void
    {
        $this->gateway->retainCandidates('old-shop');
        $this->clock->sleep(10 * 3600);
        $this->gateway->retainCandidates('old-shop');

        $retained = $this->gateway->findValid(self::APP_NAME);

        static::assertCount(3, $retained);
        foreach ($retained as $row) {
            static::assertSame('2026-01-04 22:00:00', $row['expiresAt']->format('Y-m-d H:i:s'));
        }
    }

    public function testDifferentSecretsAndShopIdsAreKeptAsSeparateRows(): void
    {
        $this->gateway->retainCandidates('old-shop');
        $this->setAppSecrets('rotated-secret', []);
        $this->gateway->retainCandidates('old-shop');
        $this->gateway->retainCandidates('other-shop');

        $pairs = array_map(
            static fn (array $row): string => $row['shopId'] . '/' . $row['secret'],
            $this->gateway->findValid(self::APP_NAME)
        );
        sort($pairs);

        static::assertSame([
            'old-shop/committed-secret',
            'old-shop/pending-newest',
            'old-shop/pending-oldest',
            'old-shop/rotated-secret',
            'other-shop/rotated-secret',
        ], $pairs);
    }

    public function testFindValidReturnsOnlyTheRequestedAppNewestFirst(): void
    {
        $this->setAppSecrets('committed-secret', []);
        $this->gateway->retainCandidates('older-shop');
        $this->clock->sleep(3600);
        $this->gateway->retainCandidates('newer-shop');
        $this->insertRetained('another-shop', 'another-app', 'foreign-secret', '2026-02-01 00:00:00.000');

        $shopIds = array_column($this->gateway->findValid(self::APP_NAME), 'shopId');

        static::assertSame(['newer-shop', 'older-shop'], $shopIds);
    }

    public function testExpiredRowsArePurgedOnRead(): void
    {
        $this->gateway->retainCandidates('old-shop');
        $this->clock->sleep(72 * 3600 + 1);

        static::assertSame([], $this->gateway->findValid(self::APP_NAME));
        static::assertSame(0, $this->retainedRowCount());
    }

    public function testExpiredRowsArePurgedOnWrite(): void
    {
        $this->gateway->retainCandidates('old-shop');
        $this->clock->sleep(72 * 3600 + 1);

        $this->gateway->retainCandidates('new-shop');

        static::assertSame(0, $this->retainedRowCount('old-shop'));
        static::assertSame(3, $this->retainedRowCount('new-shop'));
    }

    public function testPurgeExpiredKeepsRowsThatAreStillValid(): void
    {
        $this->gateway->retainCandidates('old-shop');
        $this->clock->sleep(72 * 3600 - 1);

        $this->gateway->purgeExpired();

        static::assertSame(3, $this->retainedRowCount());

        $this->clock->sleep(2);
        $this->gateway->purgeExpired();

        static::assertSame(0, $this->retainedRowCount());
    }

    /**
     * @param list<string> $unconfirmed
     */
    private function setAppSecrets(string $committed, array $unconfirmed): void
    {
        /** @var EntityRepository<AppCollection> $appRepository */
        $appRepository = static::getContainer()->get('app.repository');
        $appId = (string) $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM app WHERE name = :name', ['name' => self::APP_NAME]);

        Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($appRepository, $appId, $committed, $unconfirmed): void {
            $appRepository->update([[
                'id' => $appId,
                'appSecret' => $committed,
                'unconfirmedAppSecrets' => $unconfirmed === [] ? null : $unconfirmed,
            ]], $context);
        });
    }

    private function insertRetained(string $shopId, string $appName, string $secret, string $expiresAt): void
    {
        $this->connection->insert('app_retained_secret', [
            'shop_id' => $shopId,
            'app_name' => $appName,
            'secret' => $secret,
            'expires_at' => $expiresAt,
        ]);
    }

    private function retainedRowCount(?string $shopId = null): int
    {
        if ($shopId === null) {
            return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_retained_secret');
        }

        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_retained_secret WHERE shop_id = :shop_id', ['shop_id' => $shopId]);
    }
}
