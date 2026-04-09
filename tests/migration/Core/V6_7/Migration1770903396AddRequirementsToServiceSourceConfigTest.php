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
use Shopware\Core\Migration\V6_7\Migration1770903396AddRequirementsToServiceSourceConfig;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1770903396AddRequirementsToServiceSourceConfig::class)]
class Migration1770903396AddRequirementsToServiceSourceConfigTest extends TestCase
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
        $migration = new Migration1770903396AddRequirementsToServiceSourceConfig();
        static::assertSame(1770903396, $migration->getCreationTimestamp());
    }

    public function testMigrationBackfillsMissingRequirementsWithDefaultOnly(): void
    {
        $selfManagedMissingRequirements = $this->insertApp(
            selfManaged: true,
            sourceConfig: [
                'version' => '1.0.0',
                'hash' => 'a453f',
                'revision' => '1.0.0-a453f',
                'zip-url' => 'https://example.com/zip',
            ]
        );
        $selfManagedExistingRequirements = $this->insertApp(
            selfManaged: true,
            sourceConfig: [
                'version' => '1.0.0',
                'hash' => 'b453f',
                'revision' => '1.0.0-b453f',
                'zip-url' => 'https://example.com/zip',
                'requirements' => ['shopware_account'],
            ]
        );
        $nonSelfManagedMissingRequirements = $this->insertApp(
            selfManaged: false,
            sourceConfig: [
                'version' => '1.0.0',
                'hash' => 'c453f',
                'revision' => '1.0.0-c453f',
                'zip-url' => 'https://example.com/zip',
            ]
        );

        $before = $this->fetchSourceConfigs([
            $selfManagedMissingRequirements,
            $selfManagedExistingRequirements,
            $nonSelfManagedMissingRequirements,
        ]);

        static::assertArrayNotHasKey('requirements', $before[$selfManagedMissingRequirements]);
        static::assertSame(['shopware_account'], $before[$selfManagedExistingRequirements]['requirements']);
        static::assertArrayNotHasKey('requirements', $before[$nonSelfManagedMissingRequirements]);

        $migration = new Migration1770903396AddRequirementsToServiceSourceConfig();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $after = $this->fetchSourceConfigs([
            $selfManagedMissingRequirements,
            $selfManagedExistingRequirements,
            $nonSelfManagedMissingRequirements,
        ]);

        static::assertSame(
            ['service_consent'],
            $after[$selfManagedMissingRequirements]['requirements']
        );
        static::assertSame(
            ['shopware_account'],
            $after[$selfManagedExistingRequirements]['requirements']
        );
        static::assertArrayNotHasKey('requirements', $after[$nonSelfManagedMissingRequirements]);
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
