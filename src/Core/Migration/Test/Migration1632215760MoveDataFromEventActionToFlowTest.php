<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceCollection;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Migration\V6_4\Migration1632215760MoveDataFromEventActionToFlow;

/**
 * @internal
 */
#[Package('core')]
class Migration1632215760MoveDataFromEventActionToFlowTest extends TestCase
{
    use IntegrationTestBehaviour;

    private TestDataCollection $ids;

    private Connection $connection;

    private EntityRepository $eventActionRepository;

    private EntityRepository $flowRepository;

    protected function setUp(): void
    {
        static::markTestSkipped('NEXT-24549: should be enabled again after NEXT-24549 is fixed');

        $this->ids = new TestDataCollection();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->eventActionRepository = $this->getContainer()->get('event_action.repository');

        $this->flowRepository = $this->getContainer()->get('flow.repository');

        $this->connection->executeStatement('DELETE FROM `event_action`');
        $this->connection->executeStatement('DELETE FROM `flow`');
        $this->connection->executeStatement('DELETE FROM `sales_channel_rule`');
    }

    public function testMigrateSimpleEventActionToFlow(): void
    {
        $this->createMailTemplate();

        $eventList = [
            'user.recovery.request',
            'customer.group.registration.declined',
            'checkout.order.placed',
            'newsletter.register',
            'checkout.customer.register',
        ];

        $data = [];
        foreach ($eventList as $eventName) {
            $data[] = [
                'title' => 'Test event',
                'eventName' => $eventName,
                'actionName' => SendMailAction::getName(),
                'config' => [
                    'mail_template_id' => $this->ids->get('mail_template'),
                    'mail_template_type_id' => $this->ids->get('mail_template_type'),
                ],
                'active' => true,
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }

        $this->eventActionRepository->create($data, Context::createDefaultContext());

        $migration = new Migration1632215760MoveDataFromEventActionToFlow();
        $migration->update($this->connection);

        $flows = $this->connection->fetchFirstColumn('SELECT `event_name` FROM `flow`');

        sort($eventList);
        sort($flows);

        static::assertSame($eventList, $flows);

        $eventActions = $this->connection->fetchAllAssociative(
            'SELECT active FROM event_action WHERE action_name = :actionName',
            ['actionName' => SendMailAction::getName()]
        );

        foreach ($eventActions as $eventAction) {
            static::assertSame(0, (int) $eventAction['active']);
        }

        static::assertCount(5, $eventActions);
    }

    public function testMigrateEventActionToFlowWithSalesChannelAndRule(): void
    {
        $this->createEventActionWithSalesChannelAndRule();

        $migration = new Migration1632215760MoveDataFromEventActionToFlow();
        $migration->update($this->connection);

        $criteria = new Criteria();
        $criteria->addAssociation('sequences');

        /** @var FlowEntity $flow */
        $flow = $this->flowRepository->search($criteria, Context::createDefaultContext())->first();
        $flowSequences = $flow->getSequences();

        static::assertSame('checkout.order.placed', $flow->getEventName());
        static::assertInstanceOf(FlowSequenceCollection::class, $flowSequences);
        static::assertCount(3, $flowSequences);

        foreach ($flowSequences->getElements() as $flowSequence) {
            if ($flowSequence->getActionName() === null) {
                continue;
            }

            $expectedRecipients = [
                'test@gmail.com' => 'Test',
                'john@gmail.com' => 'John',
            ];

            $actualRecipients = $flowSequence->getConfig()['recipient']['data'];

            sort($expectedRecipients);
            sort($actualRecipients);
            static::assertSame($expectedRecipients, $actualRecipients);
            static::assertSame('custom', $flowSequence->getConfig()['recipient']['type']);
        }

        $eventActions = $this->connection->fetchAllAssociative(
            'SELECT active FROM event_action WHERE action_name = :actionName',
            ['actionName' => SendMailAction::getName()]
        );

        static::assertSame(0, (int) $eventActions[0]['active']);
        static::assertCount(1, $eventActions);
    }

    private function createEventActionWithSalesChannelAndRule(): void
    {
        $salesChannelId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM sales_channel');
        $ruleId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM rule');
        $this->createMailTemplate();
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
