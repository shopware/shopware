<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Feature;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinitionRegistry;
use Shopware\Core\Framework\App\Feature\AppFeatureException;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
class AppFeatureStorageTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private AppFixture $appFixture;

    private MockClock $clock;

    private AppFeatureStorage $storage;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->appFixture = new AppFixture(static::getContainer()->get('app.repository'));
        $this->clock = new MockClock('2026-01-01 00:00:00');
        $this->storage = new AppFeatureStorage(
            $this->connection,
            $this->clock,
            new AppFeatureDefinitionRegistry([new StubFeatureDefinition()])
        );
    }

    public function testSyncForAppInsertsNewFeatures(): void
    {
        $appId = $this->appFixture->createAppFromData(['name' => 'stub-app'])->getId();

        $this->storage->syncForApp($appId, 'stub-app', [
            ['type' => 'stub_feature', 'name' => 'one', 'payload' => ['name' => 'one', 'value' => 'first']],
            ['type' => 'stub_feature', 'name' => 'two', 'payload' => ['name' => 'two', 'value' => 'second']],
        ]);

        $rows = $this->featureRows($appId);
        static::assertSame(['one', 'two'], array_column($rows, 'name'));
    }

    public function testSyncForAppUpdatesInPlaceAndDeletesStale(): void
    {
        $appId = $this->appFixture->createAppFromData(['name' => 'stub-app'])->getId();

        $this->storage->syncForApp($appId, 'stub-app', [
            ['type' => 'stub_feature', 'name' => 'keep', 'payload' => ['name' => 'keep', 'value' => 'old']],
            ['type' => 'stub_feature', 'name' => 'drop', 'payload' => ['name' => 'drop', 'value' => 'gone']],
        ]);

        $before = $this->featureRow($appId, 'keep');
        static::assertNotNull($before);

        // advance time so the update's updated_at differs from the preserved created_at
        $this->clock->sleep(60);

        $this->storage->syncForApp($appId, 'stub-app', [
            ['type' => 'stub_feature', 'name' => 'keep', 'payload' => ['name' => 'keep', 'value' => 'new']],
            ['type' => 'stub_feature', 'name' => 'fresh', 'payload' => ['name' => 'fresh', 'value' => 'added']],
        ]);

        static::assertSame(['fresh', 'keep'], array_column($this->featureRows($appId), 'name'));
        static::assertNull($this->featureRow($appId, 'drop'));

        $after = $this->featureRow($appId, 'keep');
        static::assertNotNull($after);
        static::assertSame($before['id'], $after['id']);
        static::assertSame($before['created_at'], $after['created_at']);
        static::assertNotNull($after['updated_at']);
        static::assertNotSame($after['created_at'], $after['updated_at']);
        static::assertSame('new', Json::decodeToArray((string) $after['payload'])['value']);
    }

    public function testForActiveAppsHydratesFeaturesForActiveAppsOnly(): void
    {
        $activeId = $this->appFixture->createAppFromData(['name' => 'active-app', 'version' => '2.1.0', 'appSecret' => 's3cr3t'])->getId();
        $inactiveId = $this->appFixture->createAppFromData(['name' => 'inactive-app', 'active' => false])->getId();

        $this->insertFeature($activeId, 'active-app', 'a', 'alpha');
        $this->insertFeature($inactiveId, 'inactive-app', 'b', 'beta');

        $features = $this->storage->forActiveApps(StubFeatureConfig::class);

        static::assertCount(1, $features);
        $feature = $features[0];
        static::assertSame($activeId, $feature->appId);
        static::assertSame('active-app', $feature->appName);
        static::assertTrue($feature->appActive);
        static::assertSame('2.1.0', $feature->appVersion);
        static::assertTrue($feature->appHasSecret);
        static::assertSame('a', $feature->config->getName());
        static::assertSame('alpha', $feature->config->value);
    }

    public function testForAppReturnsFeaturesRegardlessOfActiveState(): void
    {
        $appId = $this->appFixture->createAppFromData(['name' => 'inactive-app', 'active' => false])->getId();
        $this->insertFeature($appId, 'inactive-app', 'x', 'ex');

        $features = $this->storage->forApp($appId, StubFeatureConfig::class);

        static::assertCount(1, $features);
        static::assertFalse($features[0]->appHasSecret);
        static::assertSame('ex', $features[0]->config->value);
    }

    public function testSaveRewritesDeclaredFeaturePayload(): void
    {
        $appId = $this->appFixture->createAppFromData(['name' => 'stub-app'])->getId();
        $this->insertFeature($appId, 'stub-app', 'thing', 'before');

        $this->storage->save($appId, new StubFeatureConfig('thing', 'after'));

        $row = $this->featureRow($appId, 'thing');
        static::assertNotNull($row);
        static::assertSame('after', Json::decodeToArray((string) $row['payload'])['value']);
    }

    public function testSaveThrowsWhenFeatureNotDeclared(): void
    {
        $appId = $this->appFixture->createAppFromData(['name' => 'stub-app'])->getId();

        try {
            $this->storage->save($appId, new StubFeatureConfig('missing', 'x'));
            static::fail('Expected AppFeatureException to be thrown');
        } catch (AppFeatureException $e) {
            static::assertSame(AppFeatureException::APP_FEATURE_NOT_DECLARED, $e->getErrorCode());
        }
    }

    public function testReattachKeptFeaturesRelinksOrphansByAppName(): void
    {
        // an orphaned row left by a keepUserData uninstall: app_id is null, app_name survives
        $this->insertFeature(null, 'returning-app', 'kept', 'survived');

        $appId = $this->appFixture->createAppFromData(['name' => 'returning-app'])->getId();
        $this->storage->reattachKeptFeatures($appId, 'returning-app');

        $row = $this->featureRow($appId, 'kept');
        static::assertNotNull($row);
        static::assertSame('survived', Json::decodeToArray((string) $row['payload'])['value']);
    }

    public function testDeleteForAppRemovesAllItsFeatures(): void
    {
        $appId = $this->appFixture->createAppFromData(['name' => 'stub-app'])->getId();
        $this->insertFeature($appId, 'stub-app', 'one', '1');
        $this->insertFeature($appId, 'stub-app', 'two', '2');

        $this->storage->deleteForApp($appId);

        static::assertSame([], $this->featureRows($appId));
    }

    private function insertFeature(?string $appId, string $appName, string $name, string $value): void
    {
        $this->connection->insert('app_feature', [
            'id' => Uuid::randomBytes(),
            'app_id' => $appId === null ? null : Uuid::fromHexToBytes($appId),
            'app_name' => $appName,
            'type' => 'stub_feature',
            'name' => $name,
            'payload' => Json::encode(['name' => $name, 'value' => $value]),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function featureRows(string $appId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT `id`, `name`, `payload`, `created_at`, `updated_at` FROM `app_feature` WHERE `app_id` = :id ORDER BY `name`',
            ['id' => Uuid::fromHexToBytes($appId)]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function featureRow(string $appId, string $name): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT `id`, `name`, `payload`, `created_at`, `updated_at` FROM `app_feature` WHERE `app_id` = :id AND `name` = :name',
            ['id' => Uuid::fromHexToBytes($appId), 'name' => $name]
        );

        return $row === false ? null : $row;
    }
}
