<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\Migration1539609364AddFileNameToMediaEntity;

class Migration1539609364AddFileNameToMediaEntityTest extends TestCase
{
    use IntegrationTestBehaviour;

    const CATALOG_ID = '5F1C0C3F3C574574BE8AE70933BF4BC6';

    const TRIGGER_NAME = Migration1539609364AddFileNameToMediaEntity::FORWARD_TRIGGER_NAME;

    /** @var Connection */
    private $connection;

    public function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->connection->executeUpdate(
            'INSERT INTO catalog (id, created_at) VALUES (UNHEX(?), \'2018-10-22 00:00:01.000000\')',
            [self::CATALOG_ID]
        );
    }

    public function tearDown()
    {
        $this->connection->executeUpdate('DELETE FROM catalog WHERE id = UNHEX(?)', [self::CATALOG_ID]);
    }

    public function test_column_order_is_preserved()
    {
        $tableLayout = $this->connection->fetchAll('DESCRIBE media');

        $lastTwoCols = array_slice($tableLayout, -2);

        $lastTwoCols = array_map(
            function (array $column): string {
                return $column['Field'];
            },
            $lastTwoCols
        );

        self::assertEquals(['created_at', 'updated_at'], $lastTwoCols);
    }

    public function test_db_trigger_works_correctly()
    {
        $mediaId = '34556F108AB14969A0DCF9D9522FD7DF';

        $this->connection->executeQuery('
            ALTER TABLE `media`
            DROP COLUMN `file_name`;
        ');

        $migrationStep = new Migration1539609364AddFileNameToMediaEntity();
        $migrationStep->update($this->connection);

        try {
            $this->connection->executeUpdate('
            INSERT INTO media (id, version_id, created_at)
            VALUES (UNHEX(:mediaId), 1,\'2018-10-22 00:00:01.000000\')',
                ['mediaId' => $mediaId]
            );
        } finally {
            $this->connection->exec('DROP TRIGGER ' . self::TRIGGER_NAME);
        }

        $insertedData = $this->connection->fetchAssoc('SELECT * FROM media WHERE id = UNHEX(?)', [$mediaId]);

        self::assertEquals($mediaId, $insertedData['file_name']);

        $this->connection->executeUpdate('DELETE FROM media WHERE id = UNHEX(?)', [$mediaId]);
    }
}
