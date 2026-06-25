<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1770705203AddPaymentMethodChangedFlowAndMailTemplate;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1770705203AddPaymentMethodChangedFlowAndMailTemplate::class)]
class Migration1770705203AddPaymentMethodChangedFlowAndMailTemplateTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1770705203, (new Migration1770705203AddPaymentMethodChangedFlowAndMailTemplate())->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->deleteMailTemplate();
        $this->deleteFlow();

        $migration = new Migration1770705203AddPaymentMethodChangedFlowAndMailTemplate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $mailTypeResult = $this->connection->fetchFirstColumn(
            'SELECT id FROM mail_template_type WHERE technical_name LIKE :technicalName',
            ['technicalName' => MailTemplateTypes::MAILTYPE_ORDER_PAYMENT_METHOD_CHANGED]
        );
        static::assertCount(1, $mailTypeResult);
        static::assertTrue(Uuid::isValid(Uuid::fromBytesToHex($mailTypeResult[0])));

        $mailResult = $this->connection->fetchFirstColumn(
            'SELECT id FROM mail_template WHERE mail_template_type_id = :templateTypeId',
            ['templateTypeId' => $mailTypeResult[0]]
        );
        static::assertCount(1, $mailResult);
        static::assertTrue(Uuid::isValid(Uuid::fromBytesToHex($mailResult[0])));

        $flowResult = $this->connection->fetchFirstColumn(
            'SELECT id FROM flow WHERE event_name = :eventName',
            ['eventName' => OrderPaymentMethodChangedEvent::EVENT_NAME]
        );
        static::assertCount(1, $flowResult);
        static::assertTrue(Uuid::isValid(Uuid::fromBytesToHex($flowResult[0])));

        $flowSequenceResult = $this->connection->fetchFirstColumn(
            'SELECT id FROM flow_sequence WHERE flow_id = :flowId',
            ['flowId' => $flowResult[0]]
        );
        static::assertCount(1, $flowSequenceResult);
        static::assertTrue(Uuid::isValid(Uuid::fromBytesToHex($flowSequenceResult[0])));
    }

    private function deleteMailTemplate(): void
    {
        $mailTemplateTypeByteId = $this->getMailTemplateTypeId(MailTemplateTypes::MAILTYPE_ORDER_PAYMENT_METHOD_CHANGED);
        static::assertIsString($mailTemplateTypeByteId);
        $mailTemplateByteId = $this->getMailTemplateId($mailTemplateTypeByteId);
        static::assertIsString($mailTemplateByteId);

        $this->connection->delete('mail_template', ['id' => $mailTemplateByteId]);
        $this->connection->delete('mail_template_type', ['id' => $mailTemplateTypeByteId]);

        $mailTemplateTypeByteId = $this->getMailTemplateTypeId(MailTemplateTypes::MAILTYPE_ORDER_PAYMENT_METHOD_CHANGED);
        static::assertNull($mailTemplateTypeByteId);
    }

    private function deleteFlow(): void
    {
        $flowByteId = $this->getFlowId(OrderPaymentMethodChangedEvent::EVENT_NAME);
        static::assertIsString($flowByteId);
        $mailSendSequenceByteId = $this->getFlowSequeceId($flowByteId, 'action.mail.send');
        static::assertIsString($mailSendSequenceByteId);

        $this->connection->delete('flow_sequence', ['id' => $mailSendSequenceByteId]);
        $this->connection->delete('flow', ['id' => $flowByteId]);

        $flowByteId = $this->getFlowId(OrderPaymentMethodChangedEvent::EVENT_NAME);
        static::assertNull($flowByteId);
    }

    private function getFlowId(string $eventName): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT id FROM flow WHERE event_name = :eventName',
            ['eventName' => $eventName]
        );

        if (!\is_string($result)) {
            return null;
        }

        return $result;
    }

    private function getFlowSequeceId(string $flowByteId, string $actionName): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT id FROM flow_sequence WHERE flow_id = :flowId AND action_name LIKE :actionName',
            ['flowId' => $flowByteId, 'actionName' => $actionName]
        );

        if (!\is_string($result)) {
            return null;
        }

        return $result;
    }

    private function getMailTemplateTypeId(string $technicalName): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $technicalName]
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }

    private function getMailTemplateId(string $mailTemplateTypeByteId): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId',
            ['mailTemplateTypeId' => $mailTemplateTypeByteId]
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }
}
