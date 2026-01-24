<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Migration\Traits;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Elasticsearch\Migration\Traits\ElasticsearchTriggerTrait;

/**
 * @internal
 */
#[CoversClass(ElasticsearchTriggerTrait::class)]
class ElasticsearchTriggerTraitTest extends \Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestCase
{
    use ElasticsearchTriggerTrait;

    public function testTrigger(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $this->triggerElasticsearchIndexing($connection);

        static::assertSame('["*"]', $this->fetchConfig($connection));
    }

    public function fetchConfig(Connection $connection): string
    {
        return $connection->fetchOne('SELECT `value` FROM app_config WHERE `key` = "elasticsearch.indexing.entities"');
    }
}
