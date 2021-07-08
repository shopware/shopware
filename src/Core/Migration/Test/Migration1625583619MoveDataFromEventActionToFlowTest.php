<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Action\FlowAction;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Migration\V6_4\Migration1625583619MoveDataFromEventActionToFlow;

class Migration1625583619MoveDataFromEventActionToFlowTest extends TestCase
{
    use IntegrationTestBehaviour;

    private TestDataCollection $ids;

    private ?Connection $connection;

    private ?EntityRepositoryInterface $eventActionRepository;

    private ?EntityRepositoryInterface $flowRepository;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_8225', $this);

        $this->ids = new TestDataCollection(Context::createDefaultContext());

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
                'actionName' => FlowAction::SEND_MAIL,
                'config' => [
                    'mail_template_id' => $this->ids->get('mail_template'),
                    'mail_template_type_id' => $this->ids->get('mail_template_type'),
                ],
                'active' => true,
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }

        $this->eventActionRepository->create($data, $this->ids->context);

        $migration = new Migration1625583619MoveDataFromEventActionToFlow();
        $migration->update($this->connection);

        $flows = $this->connection->fetchFirstColumn('SELECT `event_name` FROM `flow`');

        sort($eventList);
        sort($flows);

        static::assertSame($eventList, $flows);

        $eventActions = $this->connection->fetchAssociative(
            'SELECT id FROM event_action WHERE action_name = :actionName',
            ['actionName' => FlowAction::SEND_MAIL]
        );

        static::assertFalse($eventActions);
    }

    public function testMigrateEventActionToFlowWithSalesChannelAndRule(): void
    {
        $this->createEventActionWithSalesChannelAndRule();

        $migration = new Migration1625583619MoveDataFromEventActionToFlow();
        $migration->update($this->connection);

        $criteria = new Criteria();
        $criteria->addAssociation('sequences');

        /** @var FlowEntity $flow */
        $flow = $this->flowRepository->search($criteria, $this->ids->context)->first();
        $flowSequences = $flow->getSequences();

        static::assertSame('checkout.order.placed', $flow->getEventName());
        static::assertSame(3, $flowSequences->count());

        $eventActions = $this->connection->fetchAssociative(
            'SELECT id FROM event_action WHERE action_name = :actionName',
            ['actionName' => FlowAction::SEND_MAIL]
        );

        static::assertFalse($eventActions);
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
            'actionName' => FlowAction::SEND_MAIL,
            'config' => [
                'mail_template_id' => $this->ids->get('mail_template'),
                'mail_template_type_id' => $this->ids->get('mail_template_type'),
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

        $this->eventActionRepository->create([$data], $this->ids->context);
    }

    private function createMailTemplate(): void
    {
        $this->getContainer()->get('mail_template_type.repository')->create([
            [
                'id' => $this->ids->create('mail_template_type'),
                'name' => 'Test',
                'technicalName' => 'technical.name',
                'availableEntities' => [
                    'product' => 'product',
                    'salesChannel' => 'sales_channel',
                ],
            ],
        ], $this->ids->context);

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
        ], $this->ids->context);
    }
}
