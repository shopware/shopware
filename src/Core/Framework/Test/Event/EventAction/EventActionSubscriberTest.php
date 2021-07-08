<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event\EventAction;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @feature-deprecated (FEATURE_NEXT_8225) tag:v6.5.0 - Will be removed in v6.5.0.
 */
class EventActionSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDeleteCascade(): void
    {
        static::markTestSkipped('The data has been moved from Business events to Flow builder');

        $sql = '
            SELECT LOWER(HEX(id)) as id, JSON_UNQUOTE(JSON_EXTRACT(event_action.config, "$.mail_template_id")) as mail_id
            FROM event_action
            WHERE JSON_UNQUOTE(JSON_EXTRACT(event_action.config, "$.mail_template_id")) IS NOT NULL
            LIMIT 1
        ';

        $event = $this->getContainer()->get(Connection::class)
            ->fetchAssoc($sql);

        static::assertNotNull($event['id']);
        static::assertNotNull($event['mail_id']);

        $this->getContainer()->get('mail_template.repository')
            ->delete([['id' => $event['mail_id']]], Context::createDefaultContext());

        $exists = $this->getContainer()
            ->get(Connection::class)
            ->fetchColumn(
                'SELECT id FROM event_action WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($event['id'])]
            );

        static::assertFalse($exists);
    }
}
