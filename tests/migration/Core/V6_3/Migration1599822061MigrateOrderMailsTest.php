<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_3\Migration1599822061MigrateOrderMails;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1599822061MigrateOrderMails
 */
class Migration1599822061MigrateOrderMailsTest extends TestCase
{
    use MigrationTestTrait;

    public function setUp(): void
    {
        static::markTestSkipped('NEXT-24549: should be enabled again after NEXT-24549 is fixed');
    }

    public function testStateMigration(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->executeStatement('DELETE FROM event_action');

        $migration = new Migration1599822061MigrateOrderMails();
        $migration->update($connection);

        $events = $connection->fetchAllAssociative('SELECT event_name FROM event_action');
        $events = array_column($events, 'event_name');

        $expected = [
            'state_enter.order_delivery.state.cancelled',
            'state_enter.order_delivery.state.returned',
            'state_enter.order_delivery.state.returned_partially',
            'state_enter.order_delivery.state.shipped',
            'state_enter.order_delivery.state.shipped_partially',
            'state_enter.order_transaction.state.cancelled',
            'state_enter.order_transaction.state.open',
            'state_enter.order_transaction.state.paid',
            'state_enter.order_transaction.state.paid_partially',
            'state_enter.order_transaction.state.refunded',
            'state_enter.order_transaction.state.refunded_partially',
            'state_enter.order_transaction.state.reminded',
            'state_enter.order.state.cancelled',
            'state_enter.order.state.completed',
            'state_enter.order.state.in_progress',
        ];

        sort($expected);
        sort($events);

        static::assertEquals($expected, $events);
    }

    public function testMigrateEvents(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->executeStatement('DELETE FROM event_action');

        $typeId = $connection->fetchOne('SELECT LOWER(HEX(id)) FROM mail_template_type LIMIT 1');

        $connection->insert('event_action', [
            'id' => Uuid::randomBytes(),
            'action_name' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
            'config' => json_encode([
                'mail_template_type_id' => $typeId,
            ], \JSON_THROW_ON_ERROR),
            'event_name' => 'test.event',
            'active' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updated_at' => null,
        ]);

        $migration = new Migration1599822061MigrateOrderMails();
        $migration->update($connection);

        $events = $connection->fetchAllAssociative('SELECT event_name, config FROM event_action WHERE event_name = :event', ['event' => 'test.event']);

        static::assertCount(2, $events);

        foreach ($events as $event) {
            $config = json_decode((string) $event['config'], true, 512, \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('mail_template_type_id', $config);

            if (\array_key_exists('mail_template_id', $config)) {
                static::assertNotEquals($typeId, $config['mail_template_type_id']);
            } else {
                static::assertEquals($typeId, $config['mail_template_type_id']);
            }
        }
    }
}
