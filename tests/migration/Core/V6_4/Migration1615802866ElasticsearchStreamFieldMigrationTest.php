<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1615802866ElasticsearchStreamFieldMigration;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_4\Migration1615802866ElasticsearchStreamFieldMigration
 */
class Migration1615802866ElasticsearchStreamFieldMigrationTest extends TestCase
{
    use MigrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = KernelLifecycleManager::getConnection();

        $connection->executeStatement('
            INSERT INTO `product_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), \'{}\', 0, \'2019-08-16 08:43:57.488\', NULL);
        ');

        $connection->executeStatement('DELETE FROM product_stream_filter');

        $connection->executeStatement('
            INSERT INTO `product_stream_filter` (`id`, `product_stream_id`, `parent_id`, `type`, `field`, `operator`, `value`, `parameters`, `position`, `custom_fields`, `created_at`, `updated_at`)
            VALUES
                (UNHEX(\'DA6CD9776BC84463B25D5B6210DDB57B\'), UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), NULL, \'multi\', NULL, \'OR\', NULL, NULL, 0, NULL, \'2019-08-16 08:43:57.469\', NULL),
                (UNHEX(\'0EE60B6A87774E9884A832D601BE6B8F\'), UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), UNHEX(\'DA6CD9776BC84463B25D5B6210DDB57B\'), \'multi\', NULL, \'AND\', NULL, NULL, 1, NULL, \'2019-08-16 08:43:57.478\', NULL),
                (UNHEX(\'272B4392E7B34EF2ABB4827A33630C1D\'), UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), UNHEX(\'DA6CD9776BC84463B25D5B6210DDB57B\'), \'multi\', NULL, \'AND\', NULL, NULL, 3, NULL, \'2019-08-16 08:43:57.486\', NULL),
                (UNHEX(\'4A7AEB36426A482A8BFFA049F795F5E7\'), UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), UNHEX(\'DA6CD9776BC84463B25D5B6210DDB57B\'), \'multi\', NULL, \'AND\', NULL, NULL, 0, NULL, \'2019-08-16 08:43:57.470\', NULL),
                (UNHEX(\'BB87D86524FB4E7EA01EE548DD43A5AC\'), UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), UNHEX(\'DA6CD9776BC84463B25D5B6210DDB57B\'), \'multi\', NULL, \'AND\', NULL, NULL, 2, NULL, \'2019-08-16 08:43:57.483\', NULL),
                (UNHEX(\'56C5DF0B41954334A7B0CDFEDFE1D7E9\'), UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), UNHEX(\'272B4392E7B34EF2ABB4827A33630C1D\'), \'range\', \'categories.id\', NULL, NULL, \'{"lte":932,"gte":221}\', 1, NULL, \'2019-08-16 08:43:57.488\', NULL),
                (UNHEX(\'6382E03A768F444E9C2A809C63102BD4\'), UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), UNHEX(\'BB87D86524FB4E7EA01EE548DD43A5AC\'), \'range\', \'manufacturer.id\', NULL, NULL, \'{"gte":182}\', 2, NULL, \'2019-08-16 08:43:57.485\', NULL),
                (UNHEX(\'7CBC1236ABCD43CAA697E9600BF1DF6E\'), UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), UNHEX(\'4A7AEB36426A482A8BFFA049F795F5E7\'), \'range\', \'width\', NULL, NULL, \'{"lte":245}\', 1, NULL, \'2019-08-16 08:43:57.476\', NULL);
    ');
    }

    public function testFieldGetChanged(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $m = new Migration1615802866ElasticsearchStreamFieldMigration();
        $m->update($connection);

        $fields = array_values($connection->fetchAllKeyValue('SELECT id, field FROM product_stream_filter'));

        static::assertContains('manufacturerId', $fields);
        static::assertContains('categoriesRo.id', $fields);
    }
}
