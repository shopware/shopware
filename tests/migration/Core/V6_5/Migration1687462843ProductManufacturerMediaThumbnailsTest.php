<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_5\Migration1687462843ProductManufacturerMediaThumbnails;

/**
 * @internal
 */
#[CoversClass(Migration1687462843ProductManufacturerMediaThumbnails::class)]
class Migration1687462843ProductManufacturerMediaThumbnailsTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUpdate(): void
    {
        /** @var Connection $con */
        $con = $this->getContainer()->get(Connection::class);

        if (\count($this->thumbnailSizesIds($con)) > 1) {
            $this->revertMigration($con);
        }

        // 1920px is already registered
        static::assertCount(1, $this->thumbnailSizesIds($con));

        $productManufacturerFolderConfigurationId = $this->getProductManufacturerFolderConfigurationId($con);

        static::assertNotNull($productManufacturerFolderConfigurationId);

        static::assertCount(0, $this->getConfiguredThumbnailSizeIds($con, $productManufacturerFolderConfigurationId));

        $m = new Migration1687462843ProductManufacturerMediaThumbnails();
        $m->update($con);

        $thumbnailSizeIds = $this->thumbnailSizesIds($con);
        static::assertCount(3, $thumbnailSizeIds);

        $configuredThumbnailSizes = $this->getConfiguredThumbnailSizeIds($con, $productManufacturerFolderConfigurationId);
        static::assertCount(3, $configuredThumbnailSizes);

        static::assertEmpty(array_diff($thumbnailSizeIds, $configuredThumbnailSizes));

        $m->update($con);
    }

    private function revertMigration(Connection $connection): void
    {
        $thumbnailSizes = [
            ['width' => 200, 'height' => 200],
            ['width' => 360, 'height' => 360],
        ];

        foreach ($thumbnailSizes as $thumbnailSize) {
            $connection->delete(
                'media_thumbnail_size',
                ['width' => $thumbnailSize['width'], 'height' => $thumbnailSize['height']]
            );
        }

        /*
         * There is an existing entry for 1920px thumbnail, which we should not remove generally!
         * We should just remove it from the specific mediaFolder
         */
        $id_1920px = $connection->fetchOne(
            'SELECT `id` FROM `media_thumbnail_size` WHERE width = :width AND height = :height',
            ['width' => 1920, 'height' => 1920]
        );

        $connection->delete(
            'media_folder_configuration_media_thumbnail_size',
            [
                'media_folder_configuration_id' => $this->getProductManufacturerFolderConfigurationId($connection),
                'media_thumbnail_size_id' => $id_1920px,
            ]
        );
    }

    /**
     * @return array<string>
     */
    private function thumbnailSizesIds(Connection $connection): array
    {
        $thumbnailSizes = [
            ['width' => 200, 'height' => 200],
            ['width' => 360, 'height' => 360],
            ['width' => 1920, 'height' => 1920],
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

    private function getProductManufacturerFolderConfigurationId(Connection $connection): ?string
    {
        $id = $connection->fetchOne(
            'SELECT media_folder_configuration_id FROM media_folder WHERE name = :name',
            ['name' => 'Product Manufacturer Media']
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
