<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1776691515SetDefaultCmsPageIdForCategories extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776691515;
    }

    public function update(Connection $connection): void
    {
        $cmsPageId = $this->resolveDefaultCmsPageId($connection);
        if ($cmsPageId === null) {
            return;
        }

        $batchSize = 1000;
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        // `cms_page_version_id` must be written together with `cms_page_id`.
        // The composite foreign key (cms_page_id, cms_page_version_id) is not
        // enforced while `cms_page_id` IS NULL, so affected rows may carry a
        // stale, non-live version id. Setting only `cms_page_id` would then
        // activate the FK against a non-existent (page, version) pair and fail.
        do {
            $affectedRows = $connection->executeStatement(
                'UPDATE `category` SET `cms_page_id` = :cmsPageId, `cms_page_version_id` = :cmsPageVersionId WHERE `cms_page_id` IS NULL AND `type` = :type LIMIT :batchSize',
                [
                    'cmsPageId' => Uuid::fromHexToBytes($cmsPageId),
                    'cmsPageVersionId' => $liveVersionId,
                    'type' => CategoryDefinition::TYPE_PAGE,
                    'batchSize' => $batchSize,
                ],
                [
                    'cmsPageId' => ParameterType::BINARY,
                    'cmsPageVersionId' => ParameterType::BINARY,
                    'type' => ParameterType::STRING,
                    'batchSize' => ParameterType::INTEGER,
                ]
            );
        } while ($affectedRows > 0);
    }

    private function resolveDefaultCmsPageId(Connection $connection): ?string
    {
        $configurationValue = $connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :key AND `sales_channel_id` IS NULL',
            ['key' => CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY]
        );

        $configuredCmsPageId = $this->extractCmsPageId($configurationValue);
        if ($configuredCmsPageId === null) {
            return null;
        }

        if (!$this->cmsPageExists($connection, $configuredCmsPageId)) {
            return null;
        }

        return $configuredCmsPageId;
    }

    private function extractCmsPageId(mixed $configurationValue): ?string
    {
        if (!\is_string($configurationValue)) {
            return null;
        }

        $decoded = json_decode($configurationValue, true);
        if (!\is_array($decoded)) {
            return null;
        }

        $cmsPageId = $decoded['_value'] ?? null;

        if (!\is_string($cmsPageId) || $cmsPageId === '' || !Uuid::isValid($cmsPageId)) {
            return null;
        }

        return $cmsPageId;
    }

    private function cmsPageExists(Connection $connection, string $cmsPageId): bool
    {
        $cmsPageIdResult = $connection->fetchOne(
            'SELECT id FROM cms_page WHERE id = :cmsPageId AND version_id = :versionId LIMIT 1;',
            [
                'cmsPageId' => Uuid::fromHexToBytes($cmsPageId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        return $cmsPageIdResult !== false;
    }
}
