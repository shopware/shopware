<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1571990395UpdateDefaultStatusMailTemplates extends MigrationStep
{
    private ?string $defaultLangId = null;

    private ?string $deLangId = null;

    public function getCreationTimestamp(): int
    {
        return 1571990395;
    }

    public function update(Connection $connection): void
    {
        // update DELIVERY_STATE_SHIPPED_PARTIALLY
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is partially delivered',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} wurde teilweise ausgeliefert'
        );

        // update DELIVERY_STATE_RETURNED_PARTIALLY
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is partially returned',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} wurde teilweise retourniert'
        );

        // update DELIVERY_STATE_RETURNED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is returned',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} wurde retourniert'
        );

        // update DELIVERY_STATE_CANCELLED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is cancelled',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Stornierung der Bestellung bei {{ salesChannel.name }}'
        );

        // update DELIVERY_STATE_SHIPPED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is delivered',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} wurde ausgeliefert'
        );

        // update ORDER_STATE_OPEN
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is open',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} ist offen'
        );

        // update ORDER_STATE_IN_PROGRESS
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is in process',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} ist in Bearbeitung'
        );

        // update ORDER_STATE_COMPLETED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is completed',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} ist komplett abgeschlossen'
        );

        // update ORDER_STATE_CANCELLED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is cancelled',
            $this->getOrderStatusUpdateHtmlTemplateDe(),
            $this->getOrderStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Stornierung der Bestellung bei {{ salesChannel.name }}'
        );

        // update TRANSACTION_STATE_REFUNDED_PARTIALLY
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is partially refunded',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} wurde teilweise erstattet'
        );

        // update TRANSACTION_STATE_REMINDED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Reminder for your order with {{ salesChannel.name }}',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Zahlungserinnerung für die Bestellung bei {{ salesChannel.name }}'
        );

        // update TRANSACTION_STATE_OPEN
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }}',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Deine Bestellung bei {{ salesChannel.name }}'
        );

        // update TRANSACTION_STATE_PAID
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is completly paid',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Deine Bestellung bei {{ salesChannel.name }} wurde komplett bezahlt'
        );

        // update TRANSACTION_STATE_CANCELLED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'The payment for your order with {{ salesChannel.name }} is cancelled',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Die Zahlung für ihre Bestellung bei {{ salesChannel.name }} wurde storniert'
        );

        // update TRANSACTION_STATE_REFUNDED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is refunded',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Bestellung bei {{ salesChannel.name }} wurde erstattet'
        );

        // update TRANSACTION_STATE_PAID_PARTIALLY
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is partially paid',
            $this->getOrderTransactionStatusUpdateHtmlTemplateDe(),
            $this->getOrderTransactionStatusUpdatePlainTemplateDe(),
            '{{ salesChannel.name }}',
            'Deine Bestellung bei {{ salesChannel.name }} wurde teilweise bezahlt'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function changeMailTemplateNameForType(Connection $connection, string $mailTemplateType): void
    {
        $connection->executeStatement(
            'UPDATE `mail_template_type` SET `technical_name` = REPLACE(`technical_name`, \'state_enter.\', \'\')
            WHERE `technical_name` = :type',
            ['type' => $mailTemplateType]
        );
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $langId = (string) $connection->fetchOne(
            'SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id`
            WHERE `code` = :code LIMIT 1',
            ['code' => $code]
        );
        if (!$langId && $code !== 'en-GB') {
            return null;
        }

        if (!$langId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $langId;
    }

    private function updateMailTemplateTranslation(
        Connection $connection,
        string $mailTemplateType,
        string $contentHtmlEn,
        string $contentPlainEn,
        string $senderNameEn,
        string $subjectEn,
        string $contentHtmlDe,
        string $contentPlainDe,
        string $senderNameDe,
        string $subjectDe
    ): void {
        if (!$this->defaultLangId) {
            $this->defaultLangId = $this->fetchLanguageId('en-GB', $connection);
        }
        if (!$this->deLangId) {
            $this->deLangId = $this->fetchLanguageId('de-DE', $connection);
        }

        $templateTypeId = $connection->executeQuery(
            'SELECT `id` from `mail_template_type` WHERE `technical_name` = :type',
            ['type' => 'state_enter.' . $mailTemplateType]
        )->fetchOne();

        $this->changeMailTemplateNameForType($connection, 'state_enter.' . $mailTemplateType);

        $templateId = $connection->executeQuery(
            'SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `updated_at` = null',
            ['typeId' => $templateTypeId]
        )->fetchOne();

        $descriptionEn = 'Shopware Default Template';
        $descriptionDe = 'Shopware Basis Template';

        $newTemplateId = false;

        if ($templateId) {
            $connection->executeStatement(
                'UPDATE `mail_template` SET `system_default` = 1 WHERE `id`= :templateId',
                ['templateId' => $templateId]
            );
        } else {
            $newTemplateId = Uuid::randomBytes();
            $connection->insert(
                'mail_template',
                [
                    'id' => $newTemplateId,
                    'mail_template_type_id' => $templateTypeId,
                    'system_default' => true,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($this->defaultLangId !== $this->deLangId) {
            $sqlString = '';
            $sqlParams = [
                'templateId' => $templateId,
                'langId' => $this->defaultLangId,
            ];

            $sqlString .= '`content_html` = :contentHtml ';
            $sqlParams['contentHtml'] = $contentHtmlEn;

            $sqlString .= ', `content_plain` = :contentPlain ';
            $sqlParams['contentPlain'] = $contentPlainEn;

            $sqlString .= ', `sender_name` = :senderName ';
            $sqlParams['senderName'] = $senderNameEn;

            $sqlString .= ', `subject` = :subject ';
            $sqlParams['subject'] = $subjectEn;

            $sqlString .= ', `description` = :description ';
            $sqlParams['description'] = $descriptionEn;

            if ($newTemplateId) {
                $connection->insert(
                    'mail_template_translation',
                    [
                        'mail_template_id' => $newTemplateId,
                        'language_id' => $this->defaultLangId,
                        'subject' => $subjectEn,
                        'description' => $descriptionEn,
                        'sender_name' => '{{ salesChannel.name }}',
                        'content_html' => $contentHtmlEn,
                        'content_plain' => $contentPlainEn,
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );
            } else {
                $sqlString = 'UPDATE `mail_template_translation` SET ' . $sqlString
                    . 'WHERE `mail_template_id`= :templateId AND `language_id` = :langId';
                $connection->executeStatement($sqlString, $sqlParams);
            }
        }

        if ($this->deLangId) {
            $sqlString = '';
            $sqlParams = [
                'templateId' => $templateId,
                'langId' => $this->deLangId,
            ];

            $sqlString .= '`content_html` = :contentHtml ';
            $sqlParams['contentHtml'] = $contentHtmlDe;

            $sqlString .= ', `content_plain` = :contentPlain ';
            $sqlParams['contentPlain'] = $contentPlainDe;

            $sqlString .= ', `sender_name` = :senderName ';
            $sqlParams['senderName'] = $senderNameDe;

            $sqlString .= ', `subject` = :subject ';
            $sqlParams['subject'] = $subjectDe;

            $sqlString .= ', `description` = :description ';
            $sqlParams['description'] = $descriptionDe;

            $templateTranslationDeId = $connection->executeQuery(
                'SELECT `mail_template_id` from `mail_template_translation`
                    WHERE `mail_template_id` = :templateId AND language_id = :languageId',
                [
                    'templateId' => $templateId,
                    'languageId' => $this->deLangId,
                ]
            )->fetchOne();

            if ($newTemplateId || !$templateTranslationDeId) {
                $connection->insert(
                    'mail_template_translation',
                    [
                        'mail_template_id' => $newTemplateId ?: $templateId,
                        'language_id' => $this->deLangId,
                        'subject' => $subjectDe,
                        'sender_name' => '{{ salesChannel.name }}',
                        'description' => $descriptionDe,
                        'content_html' => $contentHtmlDe,
                        'content_plain' => $contentPlainDe,
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );
            } else {
                $sqlString = 'UPDATE `mail_template_translation` SET ' . $sqlString
                    . 'WHERE `mail_template_id`= :templateId AND `language_id` = :langId';
                $connection->executeStatement($sqlString, $sqlParams);
            }
        }
    }

    private function getOrderStatusUpdateHtmlTemplateEn(): string
    {
        return <<<EOT
<div style="font-family:arial; font-size:12px;">
 <br/>
    <p>
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
        <br/>
        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
        <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
        <br/>
        You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
    </p>
</div>
EOT;
    }

    private function getOrderStatusUpdatePlainTemplateEn(): string
    {
        return <<<EOT

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime.
        But in case you have purchased without a registration or a customer account, you do not have this option.
EOT;
    }

    private function getOrderStatusUpdateHtmlTemplateDe(): string
    {
        return <<<EOT
        <div style="font-family:arial; font-size:12px;">
         <br/>
            <p>
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                <br/>
                der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.</strong><br/>
                <br/>
                Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
            </p>
        </div>
EOT;
    }

    private function getOrderStatusUpdatePlainTemplateDe(): string
    {
        return <<<EOT

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen.
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
EOT;
    }

    private function getOrderTransactionStatusUpdateHtmlTemplateEn(): string
    {
        return <<<EOT
<div style="font-family:arial; font-size:12px;">
 <br/>
    <p>
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
        <br/>
        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
        <br/>
        You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
    </p>
</div>
EOT;
    }

    private function getOrderTransactionStatusUpdatePlainTemplateEn(): string
    {
        return <<<EOT

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime.
        But in case you have purchased without a registration or a customer account, you do not have this option.
EOT;
    }

    private function getOrderTransactionStatusUpdateHtmlTemplateDe(): string
    {
        return <<<EOT
        <div style="font-family:arial; font-size:12px;">
         <br/>
            <p>
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                <br/>
                der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                <br/>
                Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
            </p>
        </div>
EOT;
    }

    private function getOrderTransactionStatusUpdatePlainTemplateDe(): string
    {
        return <<<EOT

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen.
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
EOT;
    }
}
