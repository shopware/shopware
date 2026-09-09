<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1786967900AddFailedPaymentMailTemplateAndFlow;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1786967900AddFailedPaymentMailTemplateAndFlow::class)]
class Migration1786967900AddFailedPaymentMailTemplateAndFlowTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->removePreinstalledData();
    }

    public function testUpdateIsIdempotent(): void
    {
        $eventName = 'state_enter.order_transaction.state.failed';
        $migration = new Migration1786967900AddFailedPaymentMailTemplateAndFlow();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $mailTemplateTypeId = $this->getId('mail_template_type', 'technical_name', MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_FAILED);
        static::assertIsString($mailTemplateTypeId);
        static::assertTrue(Uuid::isValid(Uuid::fromBytesToHex($mailTemplateTypeId)));
        static::assertSame(1, $this->countRows('mail_template_type', 'technical_name', MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_FAILED));
        static::assertSame(1, $this->countRows('mail_template', 'mail_template_type_id', $mailTemplateTypeId));

        $flowId = $this->getId('flow', 'event_name', $eventName);
        static::assertIsString($flowId);
        static::assertSame(1, $this->countRows('flow', 'event_name', $eventName));
        static::assertSame(1, $this->countRows('flow_sequence', 'flow_id', $flowId));
        static::assertSame(1, $this->countRows('flow_sequence', 'flow_id', $flowId, 'action_name', SendMailAction::ACTION_NAME));

        $flowTemplate = $this->getFlowTemplate($eventName);
        static::assertIsArray($flowTemplate);
        static::assertSame('Payment enters status failed', $flowTemplate['name']);
        static::assertIsString($flowTemplate['config']);

        $flowTemplateConfig = json_decode($flowTemplate['config'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($flowTemplateConfig);
        static::assertSame($eventName, $flowTemplateConfig['eventName'] ?? null);

        $sequences = $flowTemplateConfig['sequences'] ?? null;
        static::assertIsArray($sequences);
        static::assertCount(1, $sequences);

        $sequence = $sequences[0];
        static::assertIsArray($sequence);
        static::assertSame(SendMailAction::ACTION_NAME, $sequence['actionName'] ?? null);

        $sequenceConfig = $sequence['config'] ?? null;
        static::assertIsArray($sequenceConfig);
        static::assertSame(Uuid::fromBytesToHex($mailTemplateTypeId), $sequenceConfig['mailTemplateTypeId'] ?? null);
    }

    public function testExistingFlowForEventIsNotModified(): void
    {
        $eventName = 'state_enter.order_transaction.state.failed';
        $customFlowId = Uuid::randomBytes();
        $this->connection->insert('flow', [
            'id' => $customFlowId,
            'name' => 'Custom failed payment flow',
            'event_name' => $eventName,
            'priority' => 1,
            'invalid' => 0,
            'active' => true,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);
        $this->connection->insert('flow_sequence', [
            'id' => Uuid::randomBytes(),
            'flow_id' => $customFlowId,
            'action_name' => 'action.add.tag',
            'config' => '{"tagId": null}',
            'display_group' => true,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);

        (new Migration1786967900AddFailedPaymentMailTemplateAndFlow())->update($this->connection);

        static::assertSame(2, $this->countRows('flow', 'event_name', $eventName));
        static::assertSame(1, $this->countRows('flow_sequence', 'flow_id', $customFlowId));
        static::assertSame(0, $this->countRows('flow_sequence', 'flow_id', $customFlowId, 'action_name', SendMailAction::ACTION_NAME));

        $defaultFlowId = $this->getId('flow', 'id', Uuid::fromHexToBytes('0accf0ae04844231af7e785e8dc94f65'));
        static::assertIsString($defaultFlowId);
        static::assertNotSame($customFlowId, $defaultFlowId);
        static::assertSame(1, $this->countRows('flow_sequence', 'flow_id', $defaultFlowId, 'action_name', SendMailAction::ACTION_NAME));
        static::assertSame(1, $this->countFlowTemplates($eventName));
    }

    private function removePreinstalledData(): void
    {
        $flowIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM flow WHERE event_name = :eventName',
            ['eventName' => 'state_enter.order_transaction.state.failed'],
        );
        foreach ($flowIds as $flowId) {
            $this->connection->delete('flow_sequence', ['flow_id' => $flowId]);
            $this->connection->delete('flow', ['id' => $flowId]);
        }

        $this->connection->executeStatement(
            'DELETE FROM `flow_template` WHERE JSON_EXTRACT(`config`, \'$.eventName\') = :eventName',
            ['eventName' => 'state_enter.order_transaction.state.failed'],
        );

        $mailTemplateTypeId = $this->getId('mail_template_type', 'technical_name', MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_FAILED);
        if ($mailTemplateTypeId === null) {
            return;
        }

        $mailTemplateId = $this->getId('mail_template', 'mail_template_type_id', $mailTemplateTypeId);
        if ($mailTemplateId !== null) {
            $this->connection->delete('mail_template_translation', ['mail_template_id' => $mailTemplateId]);
            $this->connection->delete('mail_template', ['id' => $mailTemplateId]);
        }

        $this->connection->delete('mail_template_type_translation', ['mail_template_type_id' => $mailTemplateTypeId]);
        $this->connection->delete('mail_template_type', ['id' => $mailTemplateTypeId]);
    }

    private function getId(string $table, string $column, string $value): ?string
    {
        $result = $this->connection->fetchOne(
            \sprintf('SELECT id FROM `%s` WHERE `%s` = :value LIMIT 1', $table, $column),
            ['value' => $value],
        );

        return \is_string($result) ? $result : null;
    }

    private function countRows(string $table, string $column, string $value, ?string $secondColumn = null, ?string $secondValue = null): int
    {
        $where = \sprintf('`%s` = :value', $column);
        $parameters = ['value' => $value];
        if ($secondColumn !== null && $secondValue !== null) {
            $where .= \sprintf(' AND `%s` = :secondValue', $secondColumn);
            $parameters['secondValue'] = $secondValue;
        }

        return \max(0, (int) $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $table, $where), $parameters));
    }

    /**
     * @return array{name: string, config: string}|false
     */
    private function getFlowTemplate(string $eventName): array|false
    {
        $flowTemplate = $this->connection->fetchAssociative(
            'SELECT `name`, `config` FROM `flow_template` WHERE JSON_EXTRACT(`config`, \'$.eventName\') = :eventName',
            ['eventName' => $eventName],
        );

        if (!\is_array($flowTemplate)) {
            return false;
        }

        $name = $flowTemplate['name'] ?? null;
        $config = $flowTemplate['config'] ?? null;
        if (!\is_string($name) || !\is_string($config)) {
            return false;
        }

        return [
            'name' => $name,
            'config' => $config,
        ];
    }

    private function countFlowTemplates(string $eventName): int
    {
        return \max(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `flow_template` WHERE JSON_EXTRACT(`config`, \'$.eventName\') = :eventName',
            ['eventName' => $eventName],
        ));
    }
}
