<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Content\RevocationRequest\Event\RevocationRequestEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1768545321RevocationRequestFlow extends MigrationStep
{
    final public const REVOCATION_REQUEST_FLOW_ID = '019c6fc3c6827002b7a0bc3924a077b8';

    public function getCreationTimestamp(): int
    {
        return 1768545321;
    }

    public function update(Connection $connection): void
    {
        $customerMailTemplateId = $this->getMailTemplateId($connection, MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_CUSTOMER);
        $merchantMailTemplateId = $this->getMailTemplateId($connection, MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT);

        if ($this->flowExists($connection)) {
            return;
        }

        $connection->insert(
            'flow',
            [
                'id' => Uuid::fromHexToBytes(self::REVOCATION_REQUEST_FLOW_ID),
                'name' => 'Online revocation request sent',
                'event_name' => RevocationRequestEvent::EVENT_NAME,
                'priority' => 1,
                'invalid' => 0,
                'active' => true,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        // Add send mail to customer action
        $connection->insert(
            'flow_sequence',
            [
                'id' => Uuid::randomBytes(),
                'flow_id' => Uuid::fromHexToBytes(self::REVOCATION_REQUEST_FLOW_ID),
                'action_name' => SendMailAction::ACTION_NAME,
                'config' => \json_encode([
                    'replyTo' => null,
                    'mailTemplateId' => Uuid::fromBytesToHex($customerMailTemplateId),
                    'documentTypeIds' => [],
                    'recipient' => [
                        'data' => [],
                        'type' => 'revocationRequestCustomerFormMail',
                    ],
                ], \JSON_THROW_ON_ERROR),
                'display_group' => '1',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        // Add send mail to merchant action
        $connection->insert(
            'flow_sequence',
            [
                'id' => Uuid::randomBytes(),
                'flow_id' => Uuid::fromHexToBytes(self::REVOCATION_REQUEST_FLOW_ID),
                'action_name' => SendMailAction::ACTION_NAME,
                'config' => \json_encode([
                    'replyTo' => null,
                    'mailTemplateId' => Uuid::fromBytesToHex($merchantMailTemplateId),
                    'documentTypeIds' => [],
                    'recipient' => [
                        'data' => [],
                        'type' => 'default',
                    ],
                ], \JSON_THROW_ON_ERROR),
                'position' => 1,
                'display_group' => '1',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function getMailTemplateId(Connection $connection, string $technicalName): string
    {
        $mailTemplateTypeByteId = $this->getMailTemplateTypeId($connection, $technicalName);
        $result = $connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId',
            ['mailTemplateTypeId' => $mailTemplateTypeByteId]
        );

        if ($result === false) {
            return '';
        }

        return $result;
    }

    private function getMailTemplateTypeId(Connection $connection, string $technicalName): ?string
    {
        $result = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $technicalName]
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }

    private function flowExists(Connection $connection): bool
    {
        $result = $connection->fetchOne(
            'SELECT 1 FROM `flow` WHERE `id` = :flowId',
            ['flowId' => Uuid::fromHexToBytes(self::REVOCATION_REQUEST_FLOW_ID)]
        );

        return $result !== false;
    }
}
