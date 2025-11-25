<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1763377575SendEmailAfterPasswordChangeFlow extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763377575;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->beginTransaction();

            $mailTemplateTypeId = $this->createMailTemplateType($connection);
            $mailTemplateId = $this->createMailTemplate($connection, $mailTemplateTypeId);
            $flowId = $this->createFlow($connection);
            $flowSequenceId = $this->createFlowSequence($connection, $flowId, $mailTemplateId);
            $this->createFlowTemplate($connection, $flowSequenceId, $mailTemplateId, $mailTemplateTypeId);

            $this->registerIndexer($connection, 'flow.indexer');

            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    private function createFlow(Connection $connection): string
    {
        $createdFlow = $connection->fetchOne(
            'SELECT `id` FROM `flow` WHERE `event_name` = :name',
            ['name' => CustomerPasswordChangedEvent::EVENT_NAME]
        );

        if ($createdFlow) {
            return $createdFlow;
        }

        $flowId = Uuid::randomBytes();

        $connection->insert(
            'flow',
            [
                'id' => $flowId,
                'name' => 'Send password change notification to customers',
                'event_name' => CustomerPasswordChangedEvent::EVENT_NAME,
                'active' => true,
                'payload' => null,
                'invalid' => 0,
                'custom_fields' => null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        return $flowId;
    }

    private function createFlowSequence(Connection $connection, string $flowId, string $mailTemplateId): string
    {
        $createdFlowSequence = $connection->fetchOne(
            'SELECT `id` FROM `flow_sequence` WHERE `flow_id` = :id',
            ['id' => $flowId]
        );

        if ($createdFlowSequence) {
            return $createdFlowSequence;
        }

        $flowSequenceId = Uuid::randomBytes();

        $connection->insert(
            'flow_sequence',
            [
                'id' => $flowSequenceId,
                'flow_id' => $flowId,
                'rule_id' => null,
                'parent_id' => null,
                'action_name' => SendMailAction::ACTION_NAME,
                'position' => 1,
                'true_case' => 0,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'config' => \sprintf(
                    '{"recipient": {"data": [], "type": "default"}, "mailTemplateId": "%s", "documentTypeIds": []}',
                    Uuid::fromBytesToHex($mailTemplateId)
                ),
            ]
        );

        return $flowSequenceId;
    }

    private function createFlowTemplate(Connection $connection, string $flowSequenceId, string $mailTemplateId, string $mailTemplateTypeId): void
    {
        $createdFlowTemplate = $connection->fetchOne(
            'SELECT `id` FROM `flow_template` WHERE JSON_EXTRACT(config, \'$.eventName\') = :eventName',
            ['eventName' => CustomerPasswordChangedEvent::EVENT_NAME]
        );

        if ($createdFlowTemplate) {
            return;
        }

        $connection->insert(
            'flow_template',
            [
                'id' => Uuid::randomBytes(),
                'name' => 'Send password change notification to customers',
                'config' => \sprintf(
                    '{"eventName": "%s", "sequences": [{"id": "%s", "config": {"recipient": {"data": [], "type": "default"}, "mailTemplateId": "%s", "mailTemplateTypeId": "%s"}, "ruleId": null, "parentId": null, "position": 1, "trueCase": 0, "actionName": "action.mail.send", "displayGroup": 1}], "description": null, "customFields": null}',
                    CustomerPasswordChangedEvent::EVENT_NAME,
                    Uuid::fromBytesToHex($flowSequenceId),
                    Uuid::fromBytesToHex($mailTemplateId),
                    Uuid::fromBytesToHex($mailTemplateTypeId)
                ),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function createMailTemplateType(Connection $connection): string
    {
        $createdMailTemplateType = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :name',
            ['name' => CustomerPasswordChangedEvent::EVENT_NAME]
        );

        if ($createdMailTemplateType) {
            return $createdMailTemplateType;
        }

        $mailTemplateTypeId = Uuid::randomBytes();

        $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->insert('mail_template_type', [
            'id' => $mailTemplateTypeId,
            'technical_name' => CustomerPasswordChangedEvent::EVENT_NAME,
            'available_entities' => json_encode(['customer' => 'customer']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($defaultLangId !== $deLangId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $mailTemplateTypeId,
                'language_id' => $defaultLangId,
                'name' => 'Customer password changed',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $mailTemplateTypeId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Customer password changed',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($deLangId) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $mailTemplateTypeId,
                'language_id' => $deLangId,
                'name' => 'Kundenpasswort geändert',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $mailTemplateTypeId;
    }

    private function createMailTemplate(Connection $connection, string $mailTemplateTypeId): string
    {
        $createdMailTemplate = $connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :id',
            ['id' => $mailTemplateTypeId]
        );

        if ($createdMailTemplate) {
            return $createdMailTemplate;
        }

        $mailTemplateId = Uuid::randomBytes();

        $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->insert('mail_template', [
            'id' => $mailTemplateId,
            'mail_template_type_id' => $mailTemplateTypeId,
            'system_default' => true,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($defaultLangId !== $deLangId) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $mailTemplateId,
                'language_id' => $defaultLangId,
                'sender_name' => '{{ shopName }}',
                'subject' => 'Password successfully changed',
                'description' => '',
                'content_html' => $this->getContentHtmlEn(),
                'content_plain' => $this->getContentPlainEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $mailTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'sender_name' => '{{ shopName }}',
                'subject' => 'Password successfully changed',
                'description' => '',
                'content_html' => $this->getContentHtmlEn(),
                'content_plain' => $this->getContentPlainEn(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if ($deLangId) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $mailTemplateId,
                'language_id' => $this->getLanguageIdByLocale($connection, 'de-DE'),
                'sender_name' => '{{ shopName }}',
                'subject' => 'Passwort erfolgreich geändert',
                'description' => '',
                'content_html' => $this->getContentHtmlDe(),
                'content_plain' => $this->getContentPlainDe(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $mailTemplateId;
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
        SELECT `language`.`id`
        FROM `language`
        INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
        WHERE `locale`.`code` = :code
        SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();
        if (!$languageId && $locale !== 'en-GB') {
            return null;
        }

        if (!$languageId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $languageId;
    }

    private function getContentHtmlDe(): string
    {
        return <<<MAIL
<div style="font-family:arial; font-size:12px;">
    <p>
        Hallo {{ customer.firstName }} {{ customer.lastName }},<br/>
        <br/>
        Das Passwort für Ihr {{ shopName }}-Konto wurde erfolgreich geändert. <br/>
        <br/>
        Mit freundlichen Grüßen <br/>
        Ihr {{ shopName }}-Team
    </p>
</div>
MAIL;
    }

    private function getContentPlainDe(): string
    {
        return <<<MAIL
Hallo {{ customer.firstName }} {{ customer.lastName }},

Das Passwort für Ihr {{ shopName }}-Konto wurde erfolgreich geändert.

Mit freundlichen Grüßen
Ihr {{ shopName }}-Team
MAIL;
    }

    private function getContentHtmlEn(): string
    {
        return <<<MAIL
<div style="font-family:arial; font-size:12px;">
    <p>
        Hello {{ customer.firstName }} {{ customer.lastName }},<br/>
        <br/>
        Your {{ shopName }} account password has been changed successfully. <br/>
        <br/>
        Yours sincerely <br/>
        Your {{ shopName }}-Team
    </p>
</div>
MAIL;
    }

    private function getContentPlainEn(): string
    {
        return <<<MAIL
Hello {{ customer.firstName }} {{ customer.lastName }},

Your {{ shopName }} account password has been changed successfully.

Yours sincerely
Your {{ shopName }}-Team
MAIL;
    }
}
