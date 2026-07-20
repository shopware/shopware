<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1768545320RevocationRequestCmsForm;
use Shopware\Core\Migration\V6_7\Migration1768545322AssignRevocationPageToSystemConfigSetting;
use Shopware\Core\Migration\V6_7\Migration1779173129RepairRevocationRequestCmsPageVersion;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1779173129RepairRevocationRequestCmsPageVersion::class)]
class Migration1779173129RepairRevocationRequestCmsPageVersionTest extends TestCase
{
    use MigrationTestTrait;

    private const WRONG_VERSION = '0f3f1c3f3f6a4bc2be4b3f3f752c3425';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testRepairsBrokenGlobalConfigPointingToNonLiveRevocationPage(): void
    {
        $this->deleteRevocationSystemConfig();
        $this->deleteRevocationCmsPages();

        $wrongRevocationPageId = $this->insertRevocationPage(self::WRONG_VERSION);
        $this->insertRevocationPageConfiguration($wrongRevocationPageId);

        $migration = new Migration1779173129RepairRevocationRequestCmsPageVersion();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $configuredPageId = $this->getGlobalRevocationPageConfigValue();
        static::assertIsString($configuredPageId);
        static::assertNotSame($wrongRevocationPageId, $configuredPageId);
        static::assertTrue($this->cmsPageExistsInLiveVersion($configuredPageId));
        static::assertSame(1, $this->countGlobalRevocationPageConfigurations());
        static::assertFalse($this->getGlobalRevocationButtonConfigValue());
    }

    public function testCreatesMissingGlobalConfigWhenNoRevocationPageConfigExists(): void
    {
        $this->deleteRevocationSystemConfig();
        $this->deleteRevocationCmsPages();

        $migration = new Migration1779173129RepairRevocationRequestCmsPageVersion();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $configuredPageId = $this->getGlobalRevocationPageConfigValue();
        static::assertIsString($configuredPageId);
        static::assertTrue($this->cmsPageExistsInLiveVersion($configuredPageId));
        static::assertSame(1, $this->countGlobalRevocationPageConfigurations());
        static::assertFalse($this->getGlobalRevocationButtonConfigValue());
    }

    public function testDoesNotOverwriteValidCustomLivePageConfig(): void
    {
        $this->deleteRevocationSystemConfig();
        $this->deleteRevocationCmsPages();

        $customPageId = $this->insertCmsPage(Defaults::LIVE_VERSION);
        $this->insertRevocationPageConfiguration($customPageId);

        $migration = new Migration1779173129RepairRevocationRequestCmsPageVersion();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame($customPageId, $this->getGlobalRevocationPageConfigValue());
        static::assertNull($this->getGlobalRevocationButtonConfigValue());
    }

    public function testDoesNotCreateGlobalConfigWhenOnlySalesChannelSpecificConfigExists(): void
    {
        $this->deleteRevocationSystemConfig();
        $this->deleteRevocationCmsPages();

        $customPageId = $this->insertCmsPage(Defaults::LIVE_VERSION);
        $this->insertRevocationPageConfiguration($customPageId, Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL));

        $migration = new Migration1779173129RepairRevocationRequestCmsPageVersion();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertNull($this->getGlobalRevocationPageConfigValue());
        static::assertSame($customPageId, $this->getSalesChannelRevocationPageConfigValue());
    }

    private function insertRevocationPage(string $versionId): string
    {
        $pageId = $this->insertCmsPage($versionId);

        $this->connection->insert('cms_page_translation', [
            'cms_page_id' => Uuid::fromHexToBytes($pageId),
            'cms_page_version_id' => Uuid::fromHexToBytes($versionId),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'name' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name'],
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $pageId;
    }

    private function insertCmsPage(string $versionId): string
    {
        $pageId = Uuid::randomHex();

        if ($versionId !== Defaults::LIVE_VERSION) {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO `version` (`id`, `name`, `created_at`) VALUES (:id, :name, :createdAt)',
                [
                    'id' => Uuid::fromHexToBytes($versionId),
                    'name' => 'Wrong live version test fixture',
                    'createdAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
                ['id' => ParameterType::BINARY]
            );
        }

        $this->connection->insert('cms_page', [
            'id' => Uuid::fromHexToBytes($pageId),
            'version_id' => Uuid::fromHexToBytes($versionId),
            'type' => 'page',
            'locked' => 1,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $pageId;
    }

    private function insertRevocationPageConfiguration(string $pageId, ?string $salesChannelByteId = null): void
    {
        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_PAGE_CONFIG_KEY,
            'configuration_value' => json_encode(['_value' => $pageId], \JSON_THROW_ON_ERROR),
            'sales_channel_id' => $salesChannelByteId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function deleteRevocationSystemConfig(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` IN (:pageConfigKey, :buttonConfigKey)',
            [
                'pageConfigKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_PAGE_CONFIG_KEY,
                'buttonConfigKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_BUTTON_CONFIG_KEY,
            ]
        );
    }

    private function deleteRevocationCmsPages(): void
    {
        $pageRows = $this->connection->fetchAllAssociative(
            <<<'SQL'
SELECT DISTINCT `page`.`id`, `page`.`version_id`
FROM `cms_page` AS `page`
INNER JOIN `cms_page_translation` AS `page_translation`
    ON `page_translation`.`cms_page_id` = `page`.`id`
    AND `page_translation`.`cms_page_version_id` = `page`.`version_id`
WHERE `page_translation`.`name` = :name
SQL,
            ['name' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name']]
        );

        foreach ($pageRows as $pageRow) {
            static::assertIsString($pageRow['id']);
            static::assertIsString($pageRow['version_id']);

            $this->connection->delete('cms_page', [
                'id' => $pageRow['id'],
                'version_id' => $pageRow['version_id'],
            ]);
        }
    }

    private function getGlobalRevocationPageConfigValue(): mixed
    {
        return $this->getRevocationPageConfigValue('`sales_channel_id` IS NULL');
    }

    private function getSalesChannelRevocationPageConfigValue(): mixed
    {
        return $this->getRevocationPageConfigValue('`sales_channel_id` = :salesChannelId', [
            'salesChannelId' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
        ], [
            'salesChannelId' => ParameterType::BINARY,
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, ParameterType> $types
     */
    private function getRevocationPageConfigValue(string $salesChannelCondition, array $parameters = [], array $types = []): mixed
    {
        $configurationValue = $this->connection->fetchOne(
            \sprintf(
                'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :configKey AND %s LIMIT 1',
                $salesChannelCondition
            ),
            [
                'configKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_PAGE_CONFIG_KEY,
                ...$parameters,
            ],
            $types
        );

        if (!\is_string($configurationValue)) {
            return null;
        }

        $decoded = json_decode($configurationValue, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded['_value'] ?? null;
    }

    private function getGlobalRevocationButtonConfigValue(): mixed
    {
        $configurationValue = $this->connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :configKey AND `sales_channel_id` IS NULL LIMIT 1',
            ['configKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_BUTTON_CONFIG_KEY]
        );

        if (!\is_string($configurationValue)) {
            return null;
        }

        $decoded = json_decode($configurationValue, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded['_value'] ?? null;
    }

    private function cmsPageExistsInLiveVersion(string $pageId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `cms_page` WHERE `id` = :pageId AND `version_id` = :versionId LIMIT 1',
            [
                'pageId' => Uuid::fromHexToBytes($pageId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'pageId' => ParameterType::BINARY,
                'versionId' => ParameterType::BINARY,
            ]
        );
    }

    private function countGlobalRevocationPageConfigurations(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `system_config` WHERE `configuration_key` = :configKey AND `sales_channel_id` IS NULL',
            ['configKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_PAGE_CONFIG_KEY]
        );
    }
}
