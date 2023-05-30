<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1580743279UpdateDeliveryMailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1580743279;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $enLangId = $this->fetchLanguageId('en-GB', $connection);
        $deLangId = $this->fetchLanguageId('de-DE', $connection);

        // update order confirmation
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED);
        if ($templateId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $this->getDeliveryCancellationHtmlTemplateEn(),
                $this->getDeliveryCancellationPlainTemplateEn()
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $deLangId,
                $this->getDeliveryCancellationHtmlTemplateDe(),
                $this->getDeliveryCancellationPlainTemplateDe()
            );
        }

        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED);
        if ($templateId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $this->getDeliveryReturnedHtmlTemplateEn(),
                $this->getDeliveryReturnedPlainTemplateEn()
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $deLangId,
                $this->getDeliveryReturnedHtmlTemplateDe(),
                $this->getDeliveryReturnedPlainTemplateDe()
            );
        }

        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY);
        if ($templateId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $this->getDeliveryShippedPartiallyHtmlTemplateEn(),
                $this->getDeliveryShippedPartiallyPlainTemplateEn()
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $deLangId,
                $this->getDeliveryShippedPartiallyHtmlTemplateDe(),
                $this->getDeliveryShippedPartiallyPlainTemplateDe()
            );
        }

        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED);
        if ($templateId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $this->getDeliveryShippedHtmlTemplateEn(),
                $this->getDeliveryShippedPlainTemplateEn()
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $deLangId,
                $this->getDeliveryShippedHTMLTemplateDe(),
                $this->getDeliveryShippedPlainTemplateDe()
            );
        }

        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY);
        if ($templateId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $this->getDeliveryReturnedPartiallyHtmlTemplateEn(),
                $this->getDeliveryReturnedPartiallyPlainTemplateEn()
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $deLangId,
                $this->getDeliveryReturnedPartiallyHTMLTemplateDe(),
                $this->getDeliveryReturnedPartiallyPlainTemplateDe()
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function fetchSystemMailTemplateIdFromType(Connection $connection, string $mailTemplateType): ?string
    {
        $templateTypeId = $connection->executeQuery('
        SELECT `id` from `mail_template_type` WHERE `technical_name` = :type
        ', ['type' => $mailTemplateType])->fetchOne();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchOne();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        /** @var string|null $langId */
        $langId = $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]);

        if (!$langId) {
            return null;
        }

        return $langId;
    }

    private function updateMailTemplateTranslation(
        Connection $connection,
        string $mailTemplateId,
        ?string $langId,
        ?string $contentHtml,
        ?string $contentPlain,
        ?string $senderName = null
    ): void {
        if (!$langId) {
            return;
        }

        $sqlString = '';
        $sqlParams = [
            'templateId' => $mailTemplateId,
            'langId' => $langId,
        ];

        if ($contentHtml !== null) {
            $sqlString .= '`content_html` = :contentHtml ';
            $sqlParams['contentHtml'] = $contentHtml;
        }

        if ($contentPlain !== null) {
            $sqlString .= ($sqlString !== '' ? ', ' : '') . '`content_plain` = :contentPlain ';
            $sqlParams['contentPlain'] = $contentPlain;
        }

        if ($senderName !== null) {
            $sqlString .= ($sqlString !== '' ? ', ' : '') . '`sender_name` = :senderName ';
            $sqlParams['senderName'] = $senderName;
        }

        $sqlString = 'UPDATE `mail_template_translation` SET ' . $sqlString . 'WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        $connection->executeStatement($sqlString, $sqlParams);
    }

    private function getDeliveryCancellationHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                        <br/>
                        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
                </div>';
    }

    private function getDeliveryCancellationPlainTemplateEn(): string
    {
        return '
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

            the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
            The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

            You can check the current status of your order on our website under "My account" - "My orders" anytime.
            But in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryCancellationHtmlTemplateDe(): string
    {
        return '
        <div style="font-family:arial; font-size:12px;">
           <br/>
           <p>
               {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
               <br/>
               der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
               <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
               <br/>
               Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
           </p>
        </div>';
    }

    private function getDeliveryCancellationPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Zahlungsstatus: {{order.deliveries.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen.
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getDeliveryReturnedHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                  <p>
                      {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                      <br/>
                      the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                      <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                      <br/>
                      You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
                </p>
            </div>
        ';
    }

    private function getDeliveryReturnedPlainTemplateEn(): string
    {
        return '
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

            the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
            The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

            You can check the current status of your order on our website under "My account" - "My orders" anytime.
            But in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryReturnedHtmlTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>';
    }

    private function getDeliveryReturnedPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Bestellstatus: {{order.deliveries.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen.
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getDeliveryShippedPartiallyHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
               <br/>
               <p>
                   {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                   <br/>
                   the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                   <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                   <br/>
                   You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
               </p>
            </div>
        ';
    }

    private function getDeliveryShippedPartiallyPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime.
        But in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryShippedPartiallyHtmlTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Lieferstatys für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>';
    }

    private function getDeliveryShippedPartiallyPlainTemplateDe(): string
    {
        return '
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Bestellstatus: {{order.deliveries.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen.
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getDeliveryShippedHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
                </p>
            </div>
        ';
    }

    private function getDeliveryShippedPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime.
        But in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryShippedHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getDeliveryShippedPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Bestellstatus: {{order.deliveries.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen.
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getDeliveryReturnedPartiallyHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
                </p>
            </div>
        ';
    }

    private function getDeliveryReturnedPartiallyPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime.
        But in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryReturnedPartiallyHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getDeliveryReturnedPartiallyPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Bestellstatus: {{order.deliveries.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen.
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }
}
