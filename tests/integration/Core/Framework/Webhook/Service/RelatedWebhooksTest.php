<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class RelatedWebhooksTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);

        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-1'),
            'name' => 'hook1',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-2'),
            'name' => 'hook2',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-3'),
            'name' => 'hook3',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-4'),
            'name' => 'hook4',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test2.com',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function testUpdateRelated(): void
    {
        $relatedWebhooks = static::getContainer()->get(RelatedWebhooks::class);

        $context = Context::createDefaultContext();
        $relatedWebhooks->updateRelated($this->ids->get('wh-1'), [
            'error_count' => 2,
        ], $context);

        $counts = $this->connection->fetchFirstColumn(
            'SELECT error_count FROM webhook WHERE id IN (:ids)',
            [
                'ids' => $this->ids->getByteList(['wh-1', 'wh-2', 'wh-3']),
            ],
            ['ids' => ArrayParameterType::STRING]
        );

        static::assertSame([2, 2, 2], array_map(intval(...), $counts));
    }
}
