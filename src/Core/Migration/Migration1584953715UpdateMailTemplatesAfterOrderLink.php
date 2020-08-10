<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1584953715UpdateMailTemplatesAfterOrderLink extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1584953715;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $enLangId = $this->fetchLanguageId('en-GB', $connection);
        $deLangId = $this->fetchLanguageId('de-DE', $connection);

        // update order confirmation email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            $connection,
            $enLangId,
            $deLangId,
            $this->getOrderConfirmationHtmlTemplateEn(),
            $this->getOrderConfirmationPlainTemplateEn(),
            $this->getOrderConfirmationHTMLTemplateDe(),
            $this->getOrderConfirmationPlainTemplateDe()
        );

        // update delivery email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
            $connection,
            $enLangId,
            $deLangId,
            $this->getDeliveryCancellationHtmlTemplateEn(),
            $this->getDeliveryCancellationPlainTemplateEn(),
            $this->getDeliveryCancellationHtmlTemplateDe(),
            $this->getDeliveryCancellationPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
            $connection,
            $enLangId,
            $deLangId,
            $this->getDeliveryReturnedHtmlTemplateEn(),
            $this->getDeliveryReturnedPlainTemplateEn(),
            $this->getDeliveryReturnedHtmlTemplateDe(),
            $this->getDeliveryReturnedPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
            $connection,
            $enLangId,
            $deLangId,
            $this->getDeliveryShippedPartiallyHtmlTemplateEn(),
            $this->getDeliveryShippedPartiallyPlainTemplateEn(),
            $this->getDeliveryShippedPartiallyHtmlTemplateDe(),
            $this->getDeliveryShippedPartiallyPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            $connection,
            $enLangId,
            $deLangId,
            $this->getDeliveryShippedHtmlTemplateEn(),
            $this->getDeliveryShippedPlainTemplateEn(),
            $this->getDeliveryShippedHtmlTemplateDe(),
            $this->getDeliveryShippedPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
            $connection,
            $enLangId,
            $deLangId,
            $this->getDeliveryReturnedPartiallyHtmlTemplateEn(),
            $this->getDeliveryReturnedPartiallyPlainTemplateEn(),
            $this->getDeliveryReturnedPartiallyHTMLTemplateDe(),
            $this->getDeliveryReturnedPartiallyPlainTemplateDe()
        );

        // update order email template
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
            $connection,
            $enLangId,
            $deLangId,
            $this->getOrderStateCancelledHtmlTemplateEn(),
            $this->getOrderStateCancelledPlainTemplateEn(),
            $this->getOrderStateCancelledHTMLTemplateDe(),
            $this->getOrderStateCancelledPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
            $connection,
            $enLangId,
            $deLangId,
            $this->getOrderStateOpenHtmlTemplateEn(),
            $this->getOrderStateOpenPlainTemplateEn(),
            $this->getOrderStateOpenHTMLTemplateDe(),
            $this->getOrderStateOpenPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
            $connection,
            $enLangId,
            $deLangId,
            $this->getOrderStateProgressHtmlTemplateEn(),
            $this->getOrderStateProgressPlainTemplateEn(),
            $this->getOrderStateProgressHTMLTemplateDe(),
            $this->getOrderStateProgressPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
            $connection,
            $enLangId,
            $deLangId,
            $this->getOrderStateCompletedHtmlTemplateEn(),
            $this->getOrderStateCompletedPlainTemplateEn(),
            $this->getOrderStateCompletedHTMLTemplateDe(),
            $this->getOrderStateCompletedPlainTemplateDe()
        );

        // update payment email template
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
            $connection,
            $enLangId,
            $deLangId,
            $this->getPaymentRefundPartiallyHtmlTemplateEn(),
            $this->getPaymentRefundPartiallyPlainTemplateEn(),
            $this->getPaymentRefundPartiallyHTMLTemplateDe(),
            $this->getPaymentRefundPartiallyPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
            $connection,
            $enLangId,
            $deLangId,
            $this->getPaymentRemindedHtmlTemplateEn(),
            $this->getPaymentRemindedPlainTemplateEn(),
            $this->getPaymentRemindedHTMLTemplateDe(),
            $this->getPaymentRemindedPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
            $connection,
            $enLangId,
            $deLangId,
            $this->getPaymentOpenHtmlTemplateEn(),
            $this->getPaymentOpenPlainTemplateEn(),
            $this->getPaymentOpenHTMLTemplateDe(),
            $this->getPaymentOpenPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            $connection,
            $enLangId,
            $deLangId,
            $this->getPaymentPaidHtmlTemplateEn(),
            $this->getPaymentPaidPlainTemplateEn(),
            $this->getPaymentPaidHTMLTemplateDe(),
            $this->getPaymentPaidPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            $connection,
            $enLangId,
            $deLangId,
            $this->getPaymentCancelledHtmlTemplateEn(),
            $this->getPaymentCancelledPlainTemplateEn(),
            $this->getPaymentCancelledHTMLTemplateDe(),
            $this->getPaymentCancelledPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
            $connection,
            $enLangId,
            $deLangId,
            $this->getPaymentRefundedHtmlTemplateEn(),
            $this->getPaymentRefundedPlainTemplateEn(),
            $this->getPaymentRefundedHTMLTemplateDe(),
            $this->getPaymentRefundedPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
            $connection,
            $enLangId,
            $deLangId,
            $this->getPaymentPaidPartiallyHtmlTemplateEn(),
            $this->getPaymentPaidPartiallyPlainTemplateEn(),
            $this->getPaymentPaidPartiallyHTMLTemplateDe(),
            $this->getPaymentPaidPartiallyPlainTemplateDe()
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function updateMailTemplate(
        string $mailTemplateType,
        Connection $connection,
        string $enLangId,
        string $deLangId,
        string $getHtmlTemplateEn,
        string $getPlainTemplateEn,
        string $getHtmlTemplateDe,
        string $getPlainTemplateDe
    ): void {
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, $mailTemplateType);
        if ($templateId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $getHtmlTemplateEn,
                $getPlainTemplateEn
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $deLangId,
                $getHtmlTemplateDe,
                $getPlainTemplateDe
            );
        }
    }

    private function fetchSystemMailTemplateIdFromType(Connection $connection, string $mailTemplateType): ?string
    {
        $templateTypeId = $connection->executeQuery('
        SELECT `id` from `mail_template_type` WHERE `technical_name` = :type
        ', ['type' => $mailTemplateType])->fetchColumn();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchColumn();

        if ($templateId === false || !is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        /** @var string|null $langId */
        $langId = $connection->fetchColumn('
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

        $connection->executeUpdate($sqlString, $sqlParams);
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
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
                </div>';
    }

    private function getDeliveryCancellationPlainTemplateEn(): string
    {
        return '
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

            the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
            The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
            However, in case you have purchased without a registration or a customer account, you do not have this option.';
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
               Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
               </br>
               Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
           </p>
        </div>';
    }

    private function getDeliveryCancellationPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Zahlungsstatus: {{order.deliveries.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
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
                      You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                      </br>
                      However, in case you have purchased without a registration or a customer account, you do not have this option.
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

            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
            However, in case you have purchased without a registration or a customer account, you do not have this option.';
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
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>';
    }

    private function getDeliveryReturnedPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Bestellstatus: {{order.deliveries.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
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
                   You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    However, in case you have purchased without a registration or a customer account, you do not have this option.
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

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
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
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>';
    }

    private function getDeliveryShippedPartiallyPlainTemplateDe(): string
    {
        return '
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Bestellstatus: {{order.deliveries.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
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
                    You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    However, in case you have purchased without a registration or a customer account, you do not have this option.
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

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
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
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
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
                    You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    However, in case you have purchased without a registration or a customer account, you do not have this option.
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

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
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
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getOrderStateCancelledHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
             <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    However, in case you have purchased without a registration or a customer account, you do not have this option.</p>
            </div>
        ';
    }

    private function getOrderStateCancelledPlainTemplateEn(): string
    {
        return '

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getOrderStateCancelledHTMLTemplateDe(): string
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
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getOrderStateCancelledPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getOrderStateOpenHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getOrderStateOpenPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getOrderStateOpenHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getOrderStateOpenPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getOrderStateProgressHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getOrderStateProgressPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getOrderStateProgressHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getOrderStateProgressPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getOrderStateCompletedHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                        <br/>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getOrderStateCompletedPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getOrderStateCompletedHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getOrderStateCompletedPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentRefundPartiallyHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentRefundPartiallyPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentRefundPartiallyHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getPaymentRefundPartiallyPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentRemindedHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentRemindedPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentRemindedHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getPaymentRemindedPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentOpenHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentOpenPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentOpenHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getPaymentOpenPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentPaidHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentPaidPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentPaidHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getPaymentPaidPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentCancelledHtmlTemplateEn(): string
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
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentCancelledPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentCancelledHTMLTemplateDe(): string
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
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getPaymentCancelledPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Lieferstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Zahlungsstatus: {{order.deliveries.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentRefundedHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentRefundedPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentRefundedHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getPaymentRefundedPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentPaidPartiallyHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentPaidPartiallyPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentPaidPartiallyHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.
                </p>
            </div>
        ';
    }

    private function getPaymentPaidPartiallyPlainTemplateDe(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert!
        Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getOrderConfirmationHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">

            {% set currencyIsoCode = order.currency.isoCode %}
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
            <br>
            Thank you for your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.<br>
            <br>
            <strong>Information on your order:</strong><br>
            <br>

            <table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
                <tr>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Description</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Quantities</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Price</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Total</strong></td>
                </tr>

                {% for lineItem in order.lineItems %}
                <tr>
                    <td style="border-bottom:1px solid #cccccc;">{{ loop.index }} </td>
                    <td style="border-bottom:1px solid #cccccc;">
                      {{ lineItem.label|u.wordwrap(80) }}<br>
                      {% if lineItem.payload.productNumber is defined %}Art. No.: {{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
                    </td>
                    <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
                    <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
                    <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
                </tr>
                {% endfor %}
            </table>

            {% set delivery = order.deliveries.first %}
            <p>
                <br>
                <br>
                Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>

                Net total: {{ order.amountNet|currency(currencyIsoCode) }}<br>
                {% for calculatedTax in order.price.calculatedTaxes %}
                    {% if order.taxStatus is same as(\'net\') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
                {% endfor %}
                <strong>Total gross: {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>

                <br>

                <strong>Selected payment type:</strong> {{ order.transactions.first.paymentMethod.name }}<br>
                {{ order.transactions.first.paymentMethod.description }}<br>
                <br>

                <strong>Selected shipping type:</strong> {{ delivery.shippingMethod.name }}<br>
                {{ delivery.shippingMethod.description }}<br>
                <br>

                {% set billingAddress = order.addresses.get(order.billingAddressId) %}
                <strong>Billing address:</strong><br>
                {{ billingAddress.company }}<br>
                {{ billingAddress.firstName }} {{ billingAddress.lastName }}<br>
                {{ billingAddress.street }} <br>
                {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
                {{ billingAddress.country.name }}<br>
                <br>

                <strong>Shipping address:</strong><br>
                {{ delivery.shippingOrderAddress.company }}<br>
                {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}<br>
                {{ delivery.shippingOrderAddress.street }} <br>
                {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
                {{ delivery.shippingOrderAddress.country.name }}<br>
                <br>
                {% if billingAddress.vatId %}
                    Your VAT-ID: {{ billingAddress.vatId }}
                    In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.<br>
                {% endif %}
                <br/>
                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                </br>
                If you have any questions, do not hesitate to contact us.

            </p>
            <br>
            </div>
        ';
    }

    private function getOrderConfirmationPlainTemplateEn(): string
    {
        return '
        {% set currencyIsoCode = order.currency.isoCode %}
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        Thank you for your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.

        Information on your order:

        Pos.   Art.No.			Description			Quantities			Price			Total
        {% for lineItem in order.lineItems %}
        {{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
        {% endfor %}

        {% set delivery = order.deliveries.first %}

        Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
        Net total: {{ order.amountNet|currency(currencyIsoCode) }}
            {% for calculatedTax in order.price.calculatedTaxes %}
                   {% if order.taxStatus is same as(\'net\') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}
            {% endfor %}
        Total gross: {{ order.amountTotal|currency(currencyIsoCode) }}


        Selected payment type: {{ order.transactions.first.paymentMethod.name }}
        {{ order.transactions.first.paymentMethod.description }}

        Selected shipping type: {{ delivery.shippingMethod.name }}
        {{ delivery.shippingMethod.description }}

        {% set billingAddress = order.addresses.get(order.billingAddressId) %}
        Billing address:
        {{ billingAddress.company }}
        {{ billingAddress.firstName }} {{ billingAddress.lastName }}
        {{ billingAddress.street }}
        {{ billingAddress.zipcode }} {{ billingAddress.city }}
        {{ billingAddress.country.name }}

        Shipping address:
        {{ delivery.shippingOrderAddress.company }}
        {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}
        {{ delivery.shippingOrderAddress.street }}
        {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
        {{ delivery.shippingOrderAddress.country.name }}

        {% if billingAddress.vatId %}
        Your VAT-ID: {{ billingAddress.vatId }}
        In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.
        {% endif %}

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        If you have any questions, do not hesitate to contact us.

        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getOrderConfirmationHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">

            {% set currencyIsoCode = order.currency.isoCode %}
            Hallo {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
            <br>
            vielen Dank für Ihre Bestellung im {{ salesChannel.name }} (Nummer: {{order.orderNumber}}) am {{ order.orderDateTime|date }}.<br>
            <br>
            <strong>Informationen zu Ihrer Bestellung:</strong><br>
            <br>

            <table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
                <tr>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Bezeichnung</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Menge</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Preis</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Summe</strong></td>
                </tr>

                {% for lineItem in order.lineItems %}
                <tr>
                    <td style="border-bottom:1px solid #cccccc;">{{ loop.index }} </td>
                    <td style="border-bottom:1px solid #cccccc;">
                      {{ lineItem.label|u.wordwrap(80) }}<br>
                      {% if lineItem.payload.productNumber is defined %}Artikel-Nr: {{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
                    </td>
                    <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
                    <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
                    <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
                </tr>
                {% endfor %}
            </table>

            {% set delivery = order.deliveries.first %}
            <p>
                <br>
                <br>
                Versandkosten: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
                Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}<br>
                    {% for calculatedTax in order.price.calculatedTaxes %}
                        {% if order.taxStatus is same as(\'net\') %}zzgl.{% else %}inkl.{% endif %} {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
                    {% endfor %}
                <strong>Gesamtkosten Brutto: {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
                <br>

                <strong>Gewählte Zahlungsart:</strong> {{ order.transactions.first.paymentMethod.name }}<br>
                {{ order.transactions.first.paymentMethod.description }}<br>
                <br>

                <strong>Gewählte Versandart:</strong> {{ delivery.shippingMethod.name }}<br>
                {{ delivery.shippingMethod.description }}<br>
                <br>

                {% set billingAddress = order.addresses.get(order.billingAddressId) %}
                <strong>Rechnungsadresse:</strong><br>
                {{ billingAddress.company }}<br>
                {{ billingAddress.firstName }} {{ billingAddress.lastName }}<br>
                {{ billingAddress.street }} <br>
                {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
                {{ billingAddress.country.name }}<br>
                <br>

                <strong>Lieferadresse:</strong><br>
                {{ delivery.shippingOrderAddress.company }}<br>
                {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}<br>
                {{ delivery.shippingOrderAddress.street }} <br>
                {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
                {{ delivery.shippingOrderAddress.country.name }}<br>
                <br>
                {% if billingAddress.vatId %}
                    Ihre Umsatzsteuer-ID: {{ billingAddress.vatId }}
                    Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland
                    bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit. <br>
                {% endif %}
                <br/>
                Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                </br>
                Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.

            </p>
            <br>
            </div>
        ';
    }

    private function getOrderConfirmationPlainTemplateDe(): string
    {
        return '
        {% set currencyIsoCode = order.currency.isoCode %}
        Hallo {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        vielen Dank für Ihre Bestellung im {{ salesChannel.name }} (Nummer: {{order.orderNumber}}) am {{ order.orderDateTime|date }}.

        Informationen zu Ihrer Bestellung:

        Pos.   Artikel-Nr.			Beschreibung			Menge			Preis			Summe
        {% for lineItem in order.lineItems %}
        {{ loop.index }}     {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
        {% endfor %}

        {% set delivery = order.deliveries.first %}

        Versandkosten: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
        Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}
            {% for calculatedTax in order.price.calculatedTaxes %}
                {% if order.taxStatus is same as(\'net\') %}zzgl.{% else %}inkl.{% endif %} {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}
            {% endfor %}
        Gesamtkosten Brutto: {{ order.amountTotal|currency(currencyIsoCode) }}


        Gewählte Zahlungsart: {{ order.transactions.first.paymentMethod.name }}
        {{ order.transactions.first.paymentMethod.description }}

        Gewählte Versandart: {{ delivery.shippingMethod.name }}
        {{ delivery.shippingMethod.description }}

        {% set billingAddress = order.addresses.get(order.billingAddressId) %}
        Rechnungsadresse:
        {{ billingAddress.company }}
        {{ billingAddress.firstName }} {{ billingAddress.lastName }}
        {{ billingAddress.street }}
        {{ billingAddress.zipcode }} {{ billingAddress.city }}
        {{ billingAddress.country.name }}

        Lieferadresse:
        {{ delivery.shippingOrderAddress.company }}
        {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}
        {{ delivery.shippingOrderAddress.street }}
        {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
        {{ delivery.shippingOrderAddress.country.name }}

        {% if billingAddress.vatId %}
        Ihre Umsatzsteuer-ID: {{ billingAddress.vatId }}
        Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland
        bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit.
        {% endif %}

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.';
    }
}
