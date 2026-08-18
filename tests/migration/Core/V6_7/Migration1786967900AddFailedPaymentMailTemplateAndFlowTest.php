<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
        $migration = new Migration1786967900AddFailedPaymentMailTemplateAndFlow();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $mailTemplateTypeId = $this->getId('mail_template_type', 'technical_name', MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_FAILED);
        static::assertIsString($mailTemplateTypeId);
        static::assertTrue(Uuid::isValid(Uuid::fromBytesToHex($mailTemplateTypeId)));
        static::assertSame(1, $this->countRows('mail_template_type', 'technical_name', MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_FAILED));
        static::assertSame(1, $this->countRows('mail_template', 'mail_template_type_id', $mailTemplateTypeId));

        $flowId = $this->getId('flow', 'event_name', 'state_enter.order_transaction.state.failed');
        static::assertIsString($flowId);
        static::assertSame(1, $this->countRows('flow', 'event_name', 'state_enter.order_transaction.state.failed'));
        static::assertSame(1, $this->countRows('flow_sequence', 'flow_id', $flowId));
        static::assertSame(1, $this->countRows('flow_sequence', 'flow_id', $flowId, 'action_name', 'action.mail.send'));
    }

    private function removePreinstalledData(): void
    {
        $flowId = $this->getId('flow', 'event_name', 'state_enter.order_transaction.state.failed');
        if ($flowId !== null) {
            $this->connection->delete('flow_sequence', ['flow_id' => $flowId]);
            $this->connection->delete('flow', ['id' => $flowId]);
        }

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
}
