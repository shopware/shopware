<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1676272001AddAccountTypeToCustomerProfileImportExport;

/**
 * @internal
 *
 * @phpstan-type ProfileData array{id: string, mapping: string}
 * @phpstan-type ProfileDataMappingKey array{key: string}
 */
#[CoversClass(Migration1676272001AddAccountTypeToCustomerProfileImportExport::class)]
class Migration1676272001AddAccountTypeToCustomerProfileImportExportTest extends TestCase
{
    private Connection $connection;

    private string $oldMapping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        $profile = $this->fetchProfile();
        static::assertIsArray($profile);
        $this->oldMapping = $profile['mapping'];
    }

    protected function tearDown(): void
    {
        $profile = $this->fetchProfile();
        if (!$profile) {
            $this->createProfile();
        }
    }

    public function testTimestampIsCorrect(): void
    {
        $migration = new Migration1676272001AddAccountTypeToCustomerProfileImportExport();
        static::assertEquals('1676272001', $migration->getCreationTimestamp());
    }

    public function testPreventUpdateIfProfileNotExists(): void
    {
        $this->prepare();

        $this->connection->executeQuery(
            'DELETE FROM `import_export_profile` WHERE `source_entity` =:source_entity and `name` = :name AND `system_default` = 1',
            [
                'source_entity' => CustomerDefinition::ENTITY_NAME,
                'name' => 'Default customer',
            ]
        );

        $migration = new Migration1676272001AddAccountTypeToCustomerProfileImportExport();
        $migration->update($this->connection);
    }

    public function testAddAccountTypeToProfile(): void
    {
        $this->prepare();

        $profile = $this->fetchProfile();
        static::assertIsArray($profile);
        static::assertIsString($profile['mapping']);

        $profileMapping = $this->getMappingKeys($profile['mapping']);
        $accountTypeMappings = $this->filterKeyAccountType($profileMapping);

        static::assertCount(0, $accountTypeMappings);

        $migration = new Migration1676272001AddAccountTypeToCustomerProfileImportExport();
        $migration->update($this->connection);

        $profile = $this->fetchProfile();
        static::assertIsArray($profile);
        static::assertIsString($profile['mapping']);

        $profileMapping = $this->getMappingKeys($profile['mapping']);
        $accountTypeMappings = $this->filterKeyAccountType($profileMapping);

        static::assertCount(1, $accountTypeMappings);
    }

    public function testAddAccountTypeToProfileTwice(): void
    {
        $this->prepare();

        $profile = $this->fetchProfile();
        static::assertIsArray($profile);
        static::assertIsString($profile['mapping']);

        $profileMapping = $this->getMappingKeys($profile['mapping']);
        $accountTypeMappings = $this->filterKeyAccountType($profileMapping);

        static::assertCount(0, $accountTypeMappings);

        $migration = new Migration1676272001AddAccountTypeToCustomerProfileImportExport();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $profile = $this->fetchProfile();
        static::assertIsArray($profile);
        static::assertIsString($profile['mapping']);

        $profileMapping = $this->getMappingKeys($profile['mapping']);
        $accountTypeMappings = $this->filterKeyAccountType($profileMapping);

        static::assertCount(1, $accountTypeMappings);
    }

    /**
     * @return false|ProfileData
     */
    private function fetchProfile(): false|array
    {
        /** @var false|ProfileData $profile */
        $profile = $this->connection->fetchAssociative(
            'SELECT `id`, `mapping` FROM `import_export_profile` WHERE `source_entity` =:source_entity and `name` = :name AND `system_default` = 1',
            [
                'source_entity' => CustomerDefinition::ENTITY_NAME,
                'name' => 'Default customer',
            ]
        );

        return $profile;
    }

    /**
     * @return array<ProfileDataMappingKey>
     */
    private function getMappingKeys(string $mapping): array
    {
        /** @var array<ProfileDataMappingKey> $profileMapping */
        $profileMapping = json_decode($mapping, true, 512, \JSON_THROW_ON_ERROR);

        return $profileMapping;
    }

    /**
     * @param array<ProfileDataMappingKey> $mappings
     *
     * @return array<ProfileDataMappingKey>
     */
    private function filterKeyAccountType(array $mappings): array
    {
        return array_filter($mappings, function ($mapping) {
            return $mapping['key'] === 'accountType';
        });
    }

    private function createProfile(): void
    {
        $importExportCustomerProfile = Uuid::randomBytes();

        $this->connection->insert('import_export_profile', [
            'id' => $importExportCustomerProfile,
            'name' => 'Default customer',
            'system_default' => 1,
            'source_entity' => 'customer',
            'file_type' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'mapping' => $this->oldMapping,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $languageId = [
            'english' => $this->connection->executeQuery(<<<'SQL'
                    SELECT id FROM language WHERE name = 'English'
                SQL)->fetchOne(),
            'german' => $this->connection->executeQuery(<<<'SQL'
                    SELECT id FROM language WHERE name = 'Deutsch'
                SQL)->fetchOne(),
        ];

        if ($languageId['english']) {
            $this->connection->insert('import_export_profile_translation', [
                'import_export_profile_id' => $importExportCustomerProfile,
                'language_id' => $languageId['english'],
                'label' => 'Default customer',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($languageId['german']) {
            $this->connection->insert('import_export_profile_translation', [
                'import_export_profile_id' => $importExportCustomerProfile,
                'language_id' => $languageId['german'],
                'label' => 'Standardprofil Kunde',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function prepare(): void
    {
        $profile = $this->fetchProfile();
        if (!$profile) {
            $this->createProfile();

            return;
        }

        $mappings = $this->getMappingKeys($profile['mapping']);
        $mappings = array_filter($mappings, function ($mapping) {
            return $mapping['key'] !== 'accountType';
        });

        $this->connection->update('import_export_profile', [
            'mapping' => json_encode($mappings, \JSON_THROW_ON_ERROR),
        ], ['id' => $profile['id']]);
    }
}
