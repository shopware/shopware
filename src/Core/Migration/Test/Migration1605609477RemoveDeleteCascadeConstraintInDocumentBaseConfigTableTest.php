<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_3\Migration1605609477RemoveDeleteCascadeConstraintInDocumentBaseConfigTable;

/**
 * @internal
 */
#[Package('core')]
class Migration1605609477RemoveDeleteCascadeConstraintInDocumentBaseConfigTableTest extends TestCase
{
    use KernelTestBehaviour;

    public function testUpdateDocumentBaseConfigLogoIdForeignKeyConstraintToOnDeleteSetNull(): void
    {
        $conn = $this->getContainer()->get(Connection::class);

        $database = $conn->fetchOne('select database();');

        $migration = new Migration1605609477RemoveDeleteCascadeConstraintInDocumentBaseConfigTable();
        $migration->update($conn);

        $foreignKeyInfoUpdated = $conn->fetchAssociative('SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = "document_base_config" AND REFERENCED_TABLE_NAME = "media" AND CONSTRAINT_SCHEMA = "' . $database . '";');

        static::assertIsArray($foreignKeyInfoUpdated);
        static::assertNotEmpty($foreignKeyInfoUpdated);
        static::assertEquals($foreignKeyInfoUpdated['CONSTRAINT_NAME'], 'fk.document_base_config.logo_id');
        static::assertEquals($foreignKeyInfoUpdated['DELETE_RULE'], 'SET NULL');
    }

    public function testDeleteDocumentBaseConfigLogoShouldNotDeleteDocumentBaseConfig(): void
    {
        $context = Context::createDefaultContext();

        /** @var EntityRepository $documentTypeRepository */
        $documentTypeRepository = $this->getContainer()->get('document_type.repository');
        $documentTypeId = $documentTypeRepository->searchIds(new Criteria(), $context)->firstId();
        $documentConfigId = Uuid::randomHex();

        /** @var EntityRepository $documentBaseConfigRepository */
        $documentBaseConfigRepository = $this->getContainer()->get('document_base_config.repository');

        $mediaId = Uuid::randomHex();

        /** @var EntityRepository $mediaRepository */
        $mediaRepository = $this->getContainer()->get('media.repository');

        $mediaRepository->create([
            [
                'id' => $mediaId,
            ],
        ], $context);

        $documentBaseConfigRepository->create([[
            'id' => $documentConfigId,
            'logoId' => $mediaId,
            'name' => 'test base config',
            'documentTypeId' => $documentTypeId,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], $context);

        $mediaRepository->delete([['id' => $mediaId]], $context);

        $documentConfigs = $documentBaseConfigRepository->search(new Criteria([$documentConfigId]), $context);

        static::assertNotEmpty($documentConfig = $documentConfigs->first());
        static::assertNull($documentConfig->getLogoId());
    }
}
