<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1768545320RevocationRequestCmsForm;
use Shopware\Core\Migration\V6_7\Migration1768545322AssignRevocationPageToSystemConfigSetting;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1768545322AssignRevocationPageToSystemConfigSetting::class)]
class Migration1768545322AssignRevocationPageToSystemConfigSettingTest extends TestCase
{
    private const WRONG_VERSION = '0f3f1c3f3f6a4bc2be4b3f3f752c3425';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1768545322, (new Migration1768545322AssignRevocationPageToSystemConfigSetting())->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $config = $this->getConfig();
        if ($config !== null) {
            static::assertArrayHasKey('id', $config);
            static::assertIsString($config['id']);
            $this->deleteConfigSetting($config['id']);
        }

        static::assertNull($this->getConfig());

        (new Migration1768545320RevocationRequestCmsForm())->update($this->connection);

        $migration = new Migration1768545322AssignRevocationPageToSystemConfigSetting();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $configResult = $this->getConfig();
        static::assertIsArray($configResult);
        static::assertArrayHasKey('configuration_value', $configResult);
        static::assertIsString($configResult['configuration_value']);

        $valueResult = \json_decode($configResult['configuration_value'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($valueResult);
        static::assertArrayHasKey('_value', $valueResult);
        static::assertTrue(Uuid::isValid($valueResult['_value']));

        $pageNameResult = $this->getPageName($valueResult['_value']);
        static::assertIsString($pageNameResult);
        static::assertSame(
            Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name'],
            $pageNameResult
        );
    }

    public function testUpdateDoesNotAssignNonLiveRevocationCmsPage(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->deleteRevocationPageConfig();
            $this->deleteRevocationCmsPages();

            $wrongPageByteId = Uuid::randomBytes();
            $wrongVersionByteId = Uuid::fromHexToBytes(self::WRONG_VERSION);
            $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            $this->connection->executeStatement(
                'INSERT IGNORE INTO `version` (`id`, `name`, `created_at`) VALUES (:id, :name, :createdAt)',
                [
                    'id' => $wrongVersionByteId,
                    'name' => 'Wrong live version test fixture',
                    'createdAt' => $createdAt,
                ]
            );

            $this->connection->insert('cms_page', [
                'id' => $wrongPageByteId,
                'version_id' => $wrongVersionByteId,
                'type' => 'page',
                'locked' => 1,
                'created_at' => $createdAt,
            ]);

            $this->connection->insert('cms_page_translation', [
                'cms_page_id' => $wrongPageByteId,
                'cms_page_version_id' => $wrongVersionByteId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name'],
                'created_at' => $createdAt,
            ]);

            $migration = new Migration1768545322AssignRevocationPageToSystemConfigSetting();
            $migration->update($this->connection);

            static::assertNull($this->getConfig());
        } finally {
            $this->connection->rollBack();
        }
    }

    private function getPageName(string $pageId): ?string
    {
        return $this->connection->fetchOne(
            'SELECT name FROM cms_page_translation WHERE cms_page_id = :pageId AND language_id = :languageId',
            [
                'pageId' => Uuid::fromHexToBytes($pageId),
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );
    }

    private function deleteConfigSetting(string $configByteId): void
    {
        $this->connection->delete('system_config', ['id' => $configByteId]);
    }

    private function deleteRevocationPageConfig(): void
    {
        $config = $this->getConfig();
        if ($config === null) {
            return;
        }

        static::assertIsString($config['id']);
        $this->deleteConfigSetting($config['id']);
    }

    private function deleteRevocationCmsPages(): void
    {
        $pageByteIds = $this->connection->fetchFirstColumn(
            'SELECT cms_page_id FROM cms_page_translation WHERE name = :name',
            ['name' => Migration1768545320RevocationRequestCmsForm::CMS_PAGE_TRANSLATIONS['en_name']]
        );

        foreach ($pageByteIds as $pageByteId) {
            static::assertIsString($pageByteId);
            $this->connection->delete('cms_page', ['id' => $pageByteId]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getConfig(): ?array
    {
        $result = $this->connection->fetchAssociative(
            'SELECT id, configuration_value FROM system_config WHERE configuration_key = :configKey',
            ['configKey' => Migration1768545322AssignRevocationPageToSystemConfigSetting::REVOCATION_PAGE_CONFIG_KEY]
        );

        if (!\is_array($result)) {
            return null;
        }

        return $result;
    }
}
