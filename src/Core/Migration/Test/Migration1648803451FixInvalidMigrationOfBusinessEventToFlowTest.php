<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Migration\V6_4\Migration1632215760MoveDataFromEventActionToFlow;
use Shopware\Core\Migration\V6_4\Migration1648803451FixInvalidMigrationOfBusinessEventToFlow;

/**
 * @internal
 */
#[Package('core')]
class Migration1648803451FixInvalidMigrationOfBusinessEventToFlowTest extends TestCase
{
    use IntegrationTestBehaviour;

    private TestDataCollection $ids;

    private Connection $connection;

    private EntityRepository $eventActionRepository;

    protected function setUp(): void
    {
        static::markTestSkipped('NEXT-24549: should be enabled again after NEXT-24549 is fixed');

        $this->ids = new TestDataCollection();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->eventActionRepository = $this->getContainer()->get('event_action.repository');

        $this->connection->executeStatement('DELETE FROM `event_action`');
        $this->connection->executeStatement('DELETE FROM `flow`');
        $this->connection->executeStatement('DELETE FROM `sales_channel_rule`');
    }

    public function testMigrateSimpleEventActionToFlow(): void
    {
        $data = [];
        $this->createMailTemplate();
        $ruleIds = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id FROM rule LIMIT 2');

        $data[] = [
            'title' => 'Test event',
            'eventName' => 'checkout.order.placed',
            'actionName' => SendMailAction::getName(),
            'config' => [
                'mail_template_id' => $this->ids->get('mail_template'),
                'mail_template_type_id' => $this->ids->get('mail_template_type'),
            ],
            'active' => true,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'rules' => $ruleIds,
        ];

        $this->eventActionRepository->create($data, Context::createDefaultContext());

        $migration = new Migration1632215760MoveDataFromEventActionToFlow();
        $migration->update($this->connection);

        $newMigration = new Migration1648803451FixInvalidMigrationOfBusinessEventToFlow();
        $newMigration->update($this->connection);

        $flowsSequence = $this->connection->fetchAllAssociative('SELECT * from `flow_sequence`');

        static::assertCount(4, $flowsSequence);
        $actionSequence = array_values(array_filter($flowsSequence, fn ($sequence) => $sequence['action_name'] !== null));

        static::assertCount(2, $actionSequence);
        static::assertNotEquals($actionSequence[0]['parent_id'], $actionSequence[1]['parent_id']);
    }

    public function testMigrateEventActionToFlowWithSalesChannelAndRule(): void
    {
        $this->createEventActionWithSalesChannelAndRule();

        $migration = new Migration1632215760MoveDataFromEventActionToFlow();
        $migration->update($this->connection);

        $newMigration = new Migration1648803451FixInvalidMigrationOfBusinessEventToFlow();
        $newMigration->update($this->connection);

        $flowsSequence = $this->connection->fetchAllAssociative('SELECT * from `flow_sequence`');

        static::assertCount(3, $flowsSequence);

        $saleChannelCondition = array_values(array_filter($flowsSequence, fn ($sequence) => $sequence['action_name'] === null && $sequence['parent_id'] === null))[0];

        $saleChannelRule = $this->connection->fetchOne(
            'SELECT 1 FROM `sales_channel_rule` WHERE `rule_id` = :ruleId',
            [
                'ruleId' => $saleChannelCondition['rule_id'],
            ]
        );
        static::assertEquals(1, $saleChannelRule);

        $ruleSequence = array_values(array_filter($flowsSequence, fn ($sequence) => $sequence['action_name'] === null && $sequence['parent_id'] !== null))[0];
        static::assertEquals($ruleSequence['parent_id'], $saleChannelCondition['id']);

        $actionSequence = array_values(array_filter($flowsSequence, fn ($sequence) => $sequence['action_name'] !== null && $sequence['parent_id'] !== null))[0];

        static::assertEquals($actionSequence['parent_id'], $ruleSequence['id']);
    }

    private function createEventActionWithSalesChannelAndRule(): void
    {
        $salesChannelId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM sales_channel');
        $ruleId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM rule');
        $data = [
            'id' => $this->ids->create('event_action_id'),
            'title' => 'Test event',
            'eventName' => 'checkout.order.placed',
            'actionName' => SendMailAction::getName(),
            'config' => [
                'mail_template_id' => $this->ids->get('mail_template'),
                'mail_template_type_id' => $this->ids->get('mail_template_type'),
                'recipients' => [
                    'test@gmail.com' => 'Test',
                    'john@gmail.com' => 'John',
                ],
            ],
            'active' => true,
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'salesChannels' => [
                ['id' => $salesChannelId],
            ],
            'rules' => [
                ['id' => $ruleId],
            ],
        ];

        $this->eventActionRepository->create([$data], Context::createDefaultContext());
    }

    private function createMailTemplate(): void
    {
        $this->getContainer()->get('mail_template_type.repository')->create([
            [
                'id' => $this->ids->create('mail_template_type'),
                'name' => 'Test',
                'technicalName' => 'technical.name.abc',
                'availableEntities' => [
                    'product' => 'product',
                    'salesChannel' => 'sales_channel',
                ],
            ],
        ], Context::createDefaultContext());

        $this->getContainer()->get('mail_template.repository')->create([
            [
                'id' => $this->ids->create('mail_template'),
                'mailTemplateTypeId' => $this->ids->get('mail_template_type'),
                'translations' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'subject' => 'Subject of my custom mail template',
                        'description' => 'Test mail',
                        'contentPlain' => "Hello,\nthis is the content in plain text for my custom mail template\n\nKind Regards,\nYours",
                        'contentHtml' => 'Hello,<br>this is the content in html for my custom mail template<br/><br/>Kind Regards,<br/>Yours',
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
