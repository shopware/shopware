<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Migration1555313295CreateDefaultFoldersForBasicEntities;

class Migration1555313295CreateDefaultFoldersForBasicEntitiesTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItCreatedFoldersForUnassignedDefaultFolders(): void
    {
        $folderRepository = $this->getContainer()->get('media_folder.repository');
        $defaultFolderRepository = $this->getContainer()->get('media_default_folder.repository');
        $context = Context::createDefaultContext();

        $migration = new Migration1555313295CreateDefaultFoldersForBasicEntities();

        $assignedDefaultFolderId = Uuid::randomHex();
        $unassignedDefaultFolderId = Uuid::randomHex();
        $folderId = Uuid::randomHex();

        $defaultFolderRepository->create([
            [
                'id' => $assignedDefaultFolderId,
                'entity' => 'assigned',
                'associationFields' => ['customer_id'],
            ],
            [
                'id' => $unassignedDefaultFolderId,
                'entity' => 'unassigned',
                'associationFields' => ['customer_id'],
            ],
        ], $context);

        $folderRepository->create([
            [
                'id' => $folderId,
                'name' => 'default folder for cart',
                'configuration' => [],
                'defaultFolderId' => $assignedDefaultFolderId,
            ],
        ], $context);

        $criteria = (new Criteria([$unassignedDefaultFolderId]))->addAssociation('folder');

        /** @var MediaDefaultFolderEntity $unassignedDefaultFolder */
        $unassignedDefaultFolder = $defaultFolderRepository->search($criteria, $context)->getEntities()->get($unassignedDefaultFolderId);
        static::assertNull($unassignedDefaultFolder->getFolder());

        $connection = $this->getContainer()->get('Doctrine\DBAL\Connection');
        $migration->update($connection);
        $unassignedDefaultFolder = $defaultFolderRepository->search($criteria, $context)->getEntities()->get($unassignedDefaultFolderId);

        // because entity cache is not cleared in the this test we must check if no default folder is unassigned manually
        $unassignedDefaultFolders = $connection->executeQuery('
            SELECT `media_default_folder`.`id` default_folder_id, `media_default_folder`.`entity` entity
            FROM `media_default_folder`
                LEFT JOIN `media_folder` ON `media_folder`.`default_folder_id` = `media_default_folder`.`id`
            WHERE `media_folder`.`id` IS NULL
        ')->fetchAll();

        static::assertEmpty($unassignedDefaultFolders);
    }
}
