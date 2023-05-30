<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1659257396DownloadFlow;
use Shopware\Core\Migration\V6_4\Migration1675218708UpdateDeliverOrderedProductDownloadsFlowTemplate;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1675218708UpdateDeliverOrderedProductDownloadsFlowTemplate
 */
class Migration1675218708UpdateDeliverOrderedProductDownloadsFlowTemplateTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private const FLOW_TEMPLATE_NAME = 'Deliver ordered product downloads';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->prepare();
    }

    public function testMigration(): void
    {
        $migration = new Migration1675218708UpdateDeliverOrderedProductDownloadsFlowTemplate();

        // test it can be executed multiple times
        $migration->update($this->connection);
        $migration->update($this->connection);

        $flowTemplates = $this->connection->fetchAllAssociative(
            'SELECT `id`, `config` FROM `flow_template` WHERE `name` = :name',
            ['name' => 'Deliver ordered product downloads']
        );

        static::assertCount(1, $flowTemplates);
        static::assertArrayHasKey('config', $flowTemplates[0]);

        $config = json_decode((string) $flowTemplates[0]['config'], true);
        static::assertIsArray($config);
        static::assertArrayHasKey('sequences', $config);
        static::assertCount(3, $config['sequences']);

        foreach ($config['sequences'] as $sequence) {
            static::assertArrayHasKey('config', $sequence);
            static::assertArrayHasKey('displayGroup', $sequence);
            static::assertIsArray($sequence['config']);

            if (\array_key_exists('mailTemplateId', $sequence['config'])) {
                static::assertArrayHasKey('mailTemplateTypeId', $sequence['config']);
            }
        }
    }

    private function prepare(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `flow_template` WHERE `name` = :name',
            ['name' => self::FLOW_TEMPLATE_NAME]
        );

        $migration = new Migration1659257396DownloadFlow();
        $migration->update($this->connection);
    }
}
