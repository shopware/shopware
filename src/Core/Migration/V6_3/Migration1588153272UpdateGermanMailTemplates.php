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
class Migration1588153272UpdateGermanMailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1588153272;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $deLangId = $this->fetchLanguageId('de-DE', $connection);

        if ($deLangId === null) {
            return;
        }

        // update order confirmation email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            $connection,
            $deLangId,
            $this->getOrderConfirmationHTMLTemplateDe(),
            $this->getOrderConfirmationPlainTemplateDe()
        );

        // update delivery email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
            $connection,
            $deLangId,
            $this->getDeliveryCancellationHtmlTemplateDe(),
            $this->getDeliveryCancellationPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
            $connection,
            $deLangId,
            $this->getDeliveryReturnedHtmlTemplateDe(),
            $this->getDeliveryReturnedPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
            $connection,
            $deLangId,
            $this->getDeliveryShippedPartiallyHtmlTemplateDe(),
            $this->getDeliveryShippedPartiallyPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            $connection,
            $deLangId,
            $this->getDeliveryShippedHTMLTemplateDe(),
            $this->getDeliveryShippedPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
            $connection,
            $deLangId,
            $this->getDeliveryReturnedPartiallyHTMLTemplateDe(),
            $this->getDeliveryReturnedPartiallyPlainTemplateDe()
        );

        // update order state email template
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
            $connection,
            $deLangId,
            $this->getOrderStateCancelledHTMLTemplateDe(),
            $this->getOrderStateCancelledPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
            $connection,
            $deLangId,
            $this->getOrderStateOpenHTMLTemplateDe(),
            $this->getOrderStateOpenPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
            $connection,
            $deLangId,
            $this->getOrderStateProgressHTMLTemplateDe(),
            $this->getOrderStateProgressPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
            $connection,
            $deLangId,
            $this->getOrderStateCompletedHTMLTemplateDe(),
            $this->getOrderStateCompletedPlainTemplateDe()
        );

        // update payment email template
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
            $connection,
            $deLangId,
            $this->getPaymentRefundPartiallyHTMLTemplateDe(),
            $this->getPaymentRefundPartiallyPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
            $connection,
            $deLangId,
            $this->getPaymentRemindedHTMLTemplateDe(),
            $this->getPaymentRemindedPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
            $connection,
            $deLangId,
            $this->getPaymentOpenHTMLTemplateDe(),
            $this->getPaymentOpenPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            $connection,
            $deLangId,
            $this->getPaymentPaidHTMLTemplateDe(),
            $this->getPaymentPaidPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            $connection,
            $deLangId,
            $this->getPaymentCancelledHTMLTemplateDe(),
            $this->getPaymentCancelledPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
            $connection,
            $deLangId,
            $this->getPaymentRefundedHTMLTemplateDe(),
            $this->getPaymentRefundedPlainTemplateDe()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
            $connection,
            $deLangId,
            $this->getPaymentPaidPartiallyHTMLTemplateDe(),
            $this->getPaymentPaidPartiallyPlainTemplateDe()
        );
    }

    public function updateDestructive(Connection $connection): void
    {
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

    private function updateMailTemplate(
        string $mailTemplateType,
        Connection $connection,
        string $deLangId,
        string $getHtmlTemplateDe,
        string $getPlainTemplateDe
    ): void {
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, $mailTemplateType);

        if ($templateId !== null) {
            $availableEntities = $this->fetchSystemMailTemplateAvailableEntitiesFromType($connection, $mailTemplateType);
            if (!isset($availableEntities['editOrderUrl'])) {
                $availableEntities['editOrderUrl'] = null;
                $sqlStatement = 'UPDATE `mail_template_type` SET `available_entities` = :availableEntities WHERE `technical_name` = :mailTemplateType AND `updated_at` IS NULL';
                $connection->executeStatement($sqlStatement, ['availableEntities' => json_encode($availableEntities, \JSON_THROW_ON_ERROR), 'mailTemplateType' => $mailTemplateType]);
            }

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
        ', ['type' => $mailTemplateType])->fetchOne();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchOne();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSystemMailTemplateAvailableEntitiesFromType(Connection $connection, string $mailTemplateType): array
    {
        $availableEntities = $connection->executeQuery(
            'SELECT `available_entities` FROM `mail_template_type` WHERE `technical_name` = :mailTemplateType AND updated_at IS NULL;',
            ['mailTemplateType' => $mailTemplateType]
        )->fetchOne();

        if ($availableEntities === false || !\is_string($availableEntities) || json_decode($availableEntities, true, 512, \JSON_THROW_ON_ERROR) === null) {
            return [];
        }

        return json_decode($availableEntities, true, 512, \JSON_THROW_ON_ERROR);
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
                Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.';
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
               Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
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
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
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
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
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
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
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
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
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
                    <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getOrderStateOpenHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getOrderStateProgressHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getOrderStateCompletedHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Bestellstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Bestellstatus: {{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentRefundPartiallyHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentRemindedHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentOpenHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentPaidHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
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
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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
        Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentRefundedHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }

    private function getPaymentPaidPartiallyHTMLTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    der Zahlungsstatus für Ihre Bestellung bei {{ salesChannel.name }} (Number: {{order.orderNumber}}) vom {{ order.orderDateTime|date }} hat sich geändert.<br/>
                    <strong>Die Bestellung hat jetzt den Zahlungsstatus: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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

        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.';
    }
}
