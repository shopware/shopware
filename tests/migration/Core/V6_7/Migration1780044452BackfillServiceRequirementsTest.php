<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1780044452BackfillServiceRequirements;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1780044452BackfillServiceRequirements::class)]
class Migration1780044452BackfillServiceRequirementsTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->appRepository = KernelLifecycleManager::getKernel()->getContainer()->get('app.repository');
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1780044452BackfillServiceRequirements();
        static::assertSame(1780044452, $migration->getCreationTimestamp());
    }

    public function testMigrationAddsServicesEnabledRequirementToServicesWithServiceConsent(): void
    {
        $serviceConsent = $this->insertApp(selfManaged: true, sourceConfig: [
            'version' => '1.0.0',
            'hash' => 'a453f',
            'revision' => '1.0.0-a453f',
            'zip-url' => 'https://example.com/zip',
            'requirements' => ['service_consent'],
        ]);
        $alreadyBackfilled = $this->insertApp(selfManaged: true, sourceConfig: [
            'version' => '1.0.0',
            'hash' => 'c453f',
            'revision' => '1.0.0-c453f',
            'zip-url' => 'https://example.com/zip',
            'requirements' => ['services_enabled', 'service_consent'],
        ]);
        $shopwareAccountOnly = $this->insertApp(selfManaged: true, sourceConfig: [
            'version' => '1.0.0',
            'hash' => 'd453f',
            'revision' => '1.0.0-d453f',
            'zip-url' => 'https://example.com/zip',
            'requirements' => ['shopware_account'],
        ]);
        $notSelfManaged = $this->insertApp(selfManaged: false, sourceConfig: [
            'version' => '1.0.0',
            'hash' => 'e453f',
            'revision' => '1.0.0-e453f',
            'zip-url' => 'https://example.com/zip',
            'requirements' => ['service_consent'],
        ]);

        $migration = new Migration1780044452BackfillServiceRequirements();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $sourceConfigs = $this->fetchSourceConfigs([
            $serviceConsent,
            $alreadyBackfilled,
            $shopwareAccountOnly,
            $notSelfManaged,
        ]);

        static::assertSame(['service_consent', 'services_enabled'], $sourceConfigs[$serviceConsent]['requirements']);
        static::assertSame(['services_enabled', 'service_consent'], $sourceConfigs[$alreadyBackfilled]['requirements']);
        static::assertSame(['shopware_account'], $sourceConfigs[$shopwareAccountOnly]['requirements']);
        static::assertSame(['service_consent'], $sourceConfigs[$notSelfManaged]['requirements']);
    }

    /**
     * @param array<string, mixed> $sourceConfig
     */
    private function insertApp(bool $selfManaged, array $sourceConfig): string
    {
        $appId = Uuid::randomHex();
        $appName = 'MigrationTestApp-' . Uuid::randomHex();

        $this->appRepository->create([[
            'id' => $appId,
            'name' => $appName,
            'active' => true,
            'path' => __DIR__,
            'version' => '1.0.0',
            'label' => 'migration test app',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'selfManaged' => $selfManaged,
            'sourceType' => 'local',
            'sourceConfig' => $sourceConfig,
            'integration' => [
                'label' => 'migration test app',
                'accessKey' => Uuid::randomHex(),
                'secretAccessKey' => Uuid::randomHex(),
            ],
            'aclRole' => [
                'name' => 'migration test app',
            ],
        ]], Context::createDefaultContext());

        return $appId;
    }

    /**
     * @param list<string> $appIds
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchSourceConfigs(array $appIds): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`id`)) as `id`, `source_config` FROM `app` WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($appIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $sourceConfigs = [];
        foreach ($rows as $row) {
            $decoded = json_decode($row['source_config'], true, 512, \JSON_THROW_ON_ERROR);
            \assert(\is_array($decoded));

            $sourceConfigs[$row['id']] = $decoded;
        }

        return $sourceConfigs;
    }
}
