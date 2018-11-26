<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\Migration1539609364AddFileNameToMediaEntity;

class Migration1539609364AddFileNameToMediaEntityTest extends TestCase
{
    use IntegrationTestBehaviour;

    const TRIGGER_NAME = Migration1539609364AddFileNameToMediaEntity::FORWARD_TRIGGER_NAME;

    /** @var Connection */
    private $connection;

    public function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);
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
