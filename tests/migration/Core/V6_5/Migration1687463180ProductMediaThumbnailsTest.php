<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1687463180ProductMediaThumbnails;

/**
 * @internal
 */
#[CoversClass(Migration1687463180ProductMediaThumbnails::class)]
class Migration1687463180ProductMediaThumbnailsTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUpdate(): void
    {
        /** @var Connection $con */
        $con = $this->getContainer()->get(Connection::class);

        if ($this->thumbnailSizesIds($con) !== []) {
            $this->revertMigration($con);
        }

        static::assertCount(0, $this->thumbnailSizesIds($con));

        $productManufacturerFolderConfigurationId = $this->getProductFolderConfigurationId($con);

        static::assertNotNull($productManufacturerFolderConfigurationId);

        $beforeConfiguredThumbnailSizeIds = $this->getConfiguredThumbnailSizeIds($con, $productManufacturerFolderConfigurationId);

        $m = new Migration1687463180ProductMediaThumbnails();
        $m->update($con);

        $thumbnailSizeIds = $this->thumbnailSizesIds($con);
        static::assertCount(1, $thumbnailSizeIds);

        $configuredThumbnailSizes = $this->getConfiguredThumbnailSizeIds($con, $productManufacturerFolderConfigurationId);
        static::assertCount(\count($beforeConfiguredThumbnailSizeIds) + 1, $configuredThumbnailSizes);

        $m->update($con);
    }

    private function revertMigration(Connection $connection): void
    {
        $thumbnailSizes = [
            ['width' => 280, 'height' => 280],
        ];

        foreach ($thumbnailSizes as $thumbnailSize) {
            $connection->delete(
                'media_thumbnail_size',
                ['width' => $thumbnailSize['width'], 'height' => $thumbnailSize['height']]
            );
        }
    }

    /**
     * @return array<string>
     */
    private function thumbnailSizesIds(Connection $connection): array
    {
        $thumbnailSizes = [
            ['width' => 280, 'height' => 280],
        ];

        $ids = [];
        foreach ($thumbnailSizes as $thumbnailSize) {
            $id = $connection->fetchOne(
                'SELECT id FROM media_thumbnail_size WHERE width = :width AND height = :height',
                ['width' => $thumbnailSize['width'], 'height' => $thumbnailSize['height']]
            );

            if (!empty($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function getProductFolderConfigurationId(Connection $connection): ?string
    {
        $id = $connection->fetchOne(
            'SELECT media_folder_configuration_id FROM media_folder WHERE name = :name',
            ['name' => 'Product Media']
        );

        if (\is_string($id) && $id !== '') {
            return $id;
        }

        return null;
    }

    /**
     * @return array<string>
     */
    private function getConfiguredThumbnailSizeIds(Connection $connection, string $configurationId): array
    {
        return $connection->fetchFirstColumn('
                    SELECT `media_thumbnail_size_id` FROM `media_folder_configuration_media_thumbnail_size` WHERE `media_folder_configuration_id`=:folderConfigurationId
                ', [
            'folderConfigurationId' => $configurationId,
        ]);
    }
}
