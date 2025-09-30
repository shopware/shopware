<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1739197440ChangeCmsBlockDefaultMargin extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1739197440;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $this->updateDefaultLayout($connection, 'Default listing layout');
        $this->updateDefaultLayout($connection, 'Default listing layout with sidebar');
        $this->updateDefaultLayout($connection, 'Default shop page layout with contact form');
        $this->updateDefaultLayout($connection, 'Default shop page layout with newsletter form');
    }

    /**
     * Searches for the default layout with the given translated name
     * and updates each of its blocks with the new default margins.
     *
     * @param string $layoutName - The translated name of the CMS layout.
     *
     * @throws Exception
     */
    private function updateDefaultLayout(Connection $connection, string $layoutName): void
    {
        $layoutId = $this->findDefaultLayoutId($connection, $layoutName);

        if ($layoutId) {
            $this->updateBlockDefaultMargin($connection, $layoutId);
        }
    }

    /**
     * Updates all blocks within the sections of given CMS page.
     *
     * @throws Exception
     */
    private function updateBlockDefaultMargin(Connection $connection, string $cmsPageId): void
    {
        $sectionIds = $connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id))
            FROM cms_section
            WHERE cms_page_id = :cms_page_id',
            ['cms_page_id' => $cmsPageId]
        );

        $connection->executeStatement(
            'UPDATE cms_block
            SET margin_left = null, margin_right = null
            WHERE cms_section_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($sectionIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * Retrieves a locked standard layout given by its translated name.
     *
     * @throws Exception
     */
    private function findDefaultLayoutId(Connection $connection, string $name): ?string
    {
        $result = $connection->fetchOne(
            'SELECT cms_page_id
            FROM cms_page_translation
            INNER JOIN cms_page ON cms_page.id = cms_page_translation.cms_page_id
            WHERE cms_page.locked
            AND name = :name',
            ['name' => $name]
        );

        return $result ?: null;
    }
}
