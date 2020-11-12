<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1604585230UpdateOrderMailsForCashRounding extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1604585230;
    }

    public function update(Connection $connection): void
    {
        if (!Feature::isActive('FEATURE_NEXT_6059')) {
            return;
        }

        // implement update
        $deLangId = $this->fetchLanguageId('de-DE', $connection);
        $enLangId = $this->fetchLanguageId('en-GB', $connection);

        if ($enLangId !== null) {
            // update order confirmation email templates
            $this->updateMailTemplate(
                MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
                $connection,
                $enLangId,
                $this->getOrderConfirmationHTMLTemplateEn(),
                $this->getOrderConfirmationPlainTemplateEn()
            );

            // update order transaction state paid email templates
            $this->updateMailTemplate(
                MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
                $connection,
                $enLangId,
                $this->getOrderTransactionStatePaidHTMLTemplateEn(),
                $this->getOrderTransactionStatePaidPlainTemplateEn()
            );

            // update order transaction state cancelled email templates
            $this->updateMailTemplate(
                MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
                $connection,
                $enLangId,
                $this->getOrderTransactionStateCancelledHTMLTemplateEn(),
                $this->getOrderTransactionStateCancelledPlainTemplateEn()
            );
        }

        if ($deLangId !== null) {
            // update order confirmation email templates
            $this->updateMailTemplate(
                MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
                $connection,
                $deLangId,
                $this->getOrderConfirmationHTMLTemplateDe(),
                $this->getOrderConfirmationPlainTemplateDe()
            );

            // update order transaction state paid email templates
            $this->updateMailTemplate(
                MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
                $connection,
                $deLangId,
                $this->getOrderTransactionStatePaidHTMLTemplateDe(),
                $this->getOrderTransactionStatePaidPlainTemplateDe()
            );

            // update order transaction state cancelled email templates
            $this->updateMailTemplate(
                MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
                $connection,
                $deLangId,
                $this->getOrderTransactionStateCancelledHTMLTemplateDe(),
                $this->getOrderTransactionStateCancelledPlainTemplateDe()
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
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

    private function updateMailTemplate(
        string $mailTemplateType,
        Connection $connection,
        string $deLangId,
        string $getHtmlTemplateDe,
        string $getPlainTemplateDe
    ): void {
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, $mailTemplateType);

        if ($templateId !== null) {
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

    private function updateMailTemplateTranslation(
        Connection $connection,
        string $mailTemplateId,
        ?string $langId,
        ?string $contentHtml,
        ?string $contentPlain
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

        $sqlString = 'UPDATE `mail_template_translation` SET ' . $sqlString . 'WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        $connection->executeUpdate($sqlString, $sqlParams);
    }

    private function getOrderTransactionStateCancelledPlainTemplateDe(): string
    {
        return <<<'EOF'
{% set currencyIsoCode = order.currency.isoCode %}
Hallo {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

Ihre Bestellung ist am {{ order.orderDateTime|date }} bei uns eingegangen.

Bestellnummer: {{ order.orderNumber }}

Der Zahlungsprozess mit {{ order.transactions.first.paymentMethod.name }} ist noch nicht abgeschlossen. Sie können den Zahlungsprozess über die folgende URL wieder aufnehmen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}

Informationen zu Ihrer Bestellung:

Pos.   Artikel-Nr.			Beschreibung			Menge			Preis			Summe
{% for lineItem in order.lineItems %}
{{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}				{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery = order.deliveries.first %}
{% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}

Versandkosten: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}
    {% for calculatedTax in order.price.calculatedTaxes %}
        {% if order.taxStatus is same as('net') %}zzgl.{% else %}inkl.{% endif %} {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}
    {% endfor %}
Gesamtkosten{% if displayRounded %} gerundet{% endif %} Brutto: {{ order.amountTotal|currency(currencyIsoCode) }}


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

Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.
EOF;
    }

    private function getOrderTransactionStateCancelledHTMLTemplateDe(): string
    {
        return <<<'EOF'
<div style="font-family:arial; font-size:12px;">

{% set currencyIsoCode = order.currency.isoCode %}
Hallo {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
<br>
Ihre Bestellung ist am {{ order.orderDateTime|date }} bei uns eingegangen.<br>
<br>
Bestellnummer: {{ order.orderNumber }}
<br>
Der Zahlungsprozess mit {{ order.transactions.first.paymentMethod.name }} ist noch nicht abgeschlossen. Sie können den Zahlungsprozess über die folgende URL wieder aufnehmen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}<br>
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
            {% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}
                {% for option in lineItem.payload.options %}
                    {{ option.group }}: {{ option.option }}
                    {% if lineItem.payload.options|last != option %}
                        {{ " | " }}
                    {% endif %}
                {% endfor %}
                <br/>
            {% endif %}
          {% if lineItem.payload.productNumber is defined %}Artikel-Nr: {{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
        </td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
    </tr>
    {% endfor %}
</table>

{% set delivery = order.deliveries.first %}
{% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
<p>
    <br>
    <br>
    Versandkosten: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
    Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}<br>
        {% for calculatedTax in order.price.calculatedTaxes %}
            {% if order.taxStatus is same as('net') %}zzgl.{% else %}inkl.{% endif %} {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
        {% endfor %}
    <strong>Gesamtkosten{% if displayRounded %} gerundet{% endif %} Brutto: {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
    <br>

    <strong>Gewählte Zahlungsart:</strong> {{ order.transactions.first.paymentMethod.translated.name }}<br>
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
    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
    </br>
    Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.
</p>
<br>
</div>
EOF;
    }

    private function getOrderTransactionStateCancelledPlainTemplateEn(): string
    {
        return <<<'EOF'
{% set currencyIsoCode = order.currency.isoCode %}
{{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

We have received your order on {{ order.orderDateTime|date }}.

Order number: {{ order.orderNumber }}.

You have not completed your payment with {{ order.transactions.first.paymentMethod.name }} yet. You can resume the payment process by using the following URL: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}

Information on your order:

Pos.   Prod. No.			Description			Quantities			Price			Total
{% for lineItem in order.lineItems %}
{{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}				{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery = order.deliveries.first %}
{% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
{% set total = order.price.totalPrice %}

{% if displayRounded %}
    {% set total = order.price.rawTotal %}
{% endif %}

Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
Net total: {{ order.amountNet|currency(currencyIsoCode) }}
    {% for calculatedTax in order.price.calculatedTaxes %}
           {% if order.taxStatus is same as('net') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}
    {% endfor %}
Total gross: {{ total|currency(currencyIsoCode) }}
{% if displayRounded %}
Rounded total gross: {{ order.price.totalPrice|currency(currencyIsoCode) }}
{% endif %}

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

You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
If you have any questions, do not hesitate to contact us.

However, in case you have purchased without a registration or a customer account, you do not have this option.
EOF;
    }

    private function getOrderTransactionStateCancelledHTMLTemplateEn(): string
    {
        return <<<'EOF'
<div style="font-family:arial; font-size:12px;">

{% set currencyIsoCode = order.currency.isoCode %}
{{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
<br>
We have received your order on {{ order.orderDateTime|date }}.<br>
<br>
Order number: {{ order.orderNumber }}.<br>
<br>
You have not completed your payment with {{ order.transactions.first.paymentMethod.name }} yet. You can resume the payment process by using the following URL: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}<br>
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
            {% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}
                {% for option in lineItem.payload.options %}
                    {{ option.group }}: {{ option.option }}
                    {% if lineItem.payload.options|last != option %}
                        {{ " | " }}
                    {% endif %}
                {% endfor %}
                <br/>
            {% endif %}
          {% if lineItem.payload.productNumber is defined %}Prod. No.: {{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
        </td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
    </tr>
    {% endfor %}
</table>

{% set delivery = order.deliveries.first %}
{% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
{% set total = order.price.totalPrice %}

{% if displayRounded %}
    {% set total = order.price.rawTotal %}
{% endif %}
<p>
    <br>
    <br>
    Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>

    Net total: {{ order.amountNet|currency(currencyIsoCode) }}<br>
    {% for calculatedTax in order.price.calculatedTaxes %}
        {% if order.taxStatus is same as('net') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
    {% endfor %}
    {% if not displayRounded %}<strong>{% endif %}Total gross: {{ total|currency(currencyIsoCode) }}{% if not displayRounded %}<strong>{% endif %}<br>
    {% if displayRounded %}
        <strong>Rounded total gross: {{ order.price.totalPrice|currency(currencyIsoCode) }}<strong><br>
    {% endif %}

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
    You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
    </br>
    If you have any questions, do not hesitate to contact us.

</p>
<br>
</div>
EOF;
    }

    private function getOrderTransactionStatePaidPlainTemplateDe(): string
    {
        return <<<'EOF'
{% set currencyIsoCode = order.currency.isoCode %}
Hallo {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

Wir haben Ihre Zahlung erhalten und werden die Bestellung nun weiter verarbeiten.

Bestellnummer: {{ order.orderNumber }}

Informationen zu Ihrer Bestellung:

Pos.   Artikel-Nr.			Beschreibung			Menge			Preis			Summe
{% for lineItem in order.lineItems %}
{{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}				{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery = order.deliveries.first %}
{% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}

Versandkosten: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}
    {% for calculatedTax in order.price.calculatedTaxes %}
        {% if order.taxStatus is same as('net') %}zzgl.{% else %}inkl.{% endif %} {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}
    {% endfor %}
Gesamtkosten{% if displayRounded %} gerundet{% endif %} Brutto: {{ order.amountTotal|currency(currencyIsoCode) }}


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

Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.
EOF;
    }

    private function getOrderTransactionStatePaidHTMLTemplateDe(): string
    {
        return <<<'EOF'
<div style="font-family:arial; font-size:12px;">

    {% set currencyIsoCode = order.currency.isoCode %}
    Hallo {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
    <br>
    Wir haben Ihre Zahlung erhalten und werden die Bestellung nun weiter verarbeiten.<br>
    <br>
    Bestellnummer: {{ order.orderNumber }}<br>
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
                {% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}
                    {% for option in lineItem.payload.options %}
                        {{ option.group }}: {{ option.option }}
                        {% if lineItem.payload.options|last != option %}
                            {{ " | " }}
                        {% endif %}
                    {% endfor %}
                    <br/>
                {% endif %}
              {% if lineItem.payload.productNumber is defined %}Artikel-Nr: {{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
            </td>
            <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
            <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
            <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
        </tr>
        {% endfor %}
    </table>

    {% set delivery = order.deliveries.first %}
    {% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
    <p>
        <br>
        <br>
        Versandkosten: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
        Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}<br>
            {% for calculatedTax in order.price.calculatedTaxes %}
                {% if order.taxStatus is same as('net') %}zzgl.{% else %}inkl.{% endif %} {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
            {% endfor %}
        <strong>Gesamtkosten{% if displayRounded %} gerundet{% endif %} Brutto: {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
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
        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
        </br>
        Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.
    </p>
    <br>
    </div>
EOF;
    }

    private function getOrderTransactionStatePaidPlainTemplateEn(): string
    {
        return <<<'EOF'
{% set currencyIsoCode = order.currency.isoCode %}
{{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

We received your payment and will now start processing the order.

Order number: {{ order.orderNumber }}


Information on your order:

Pos.   Prod. No.			Description			Quantities			Price			Total
{% for lineItem in order.lineItems %}
{{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}				{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery = order.deliveries.first %}
{% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
{% set total = order.price.totalPrice %}

{% if displayRounded %}
    {% set total = order.price.rawTotal %}
{% endif %}

Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
Net total: {{ order.amountNet|currency(currencyIsoCode) }}
    {% for calculatedTax in order.price.calculatedTaxes %}
           {% if order.taxStatus is same as('net') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}
    {% endfor %}
Total gross: {{ total|currency(currencyIsoCode) }}
{% if displayRounded %}
Rounded total gross: {{ order.price.totalPrice|currency(currencyIsoCode) }}
{% endif %}

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

You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
If you have any questions, do not hesitate to contact us.

However, in case you have purchased without a registration or a customer account, you do not have this option.
EOF;
    }

    private function getOrderTransactionStatePaidHTMLTemplateEn(): string
    {
        return <<<'EOF'
<div style="font-family:arial; font-size:12px;">

    {% set currencyIsoCode = order.currency.isoCode %}
    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
    <br>
    We received your payment and will now start processing the order.<br>
    Order number: {{ order.orderNumber }}<br>
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
                {% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}
                    {% for option in lineItem.payload.options %}
                        {{ option.group }}: {{ option.option }}
                        {% if lineItem.payload.options|last != option %}
                            {{ " | " }}
                        {% endif %}
                    {% endfor %}
                    <br/>
                {% endif %}
              {% if lineItem.payload.productNumber is defined %}Prod. No.: {{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
            </td>
            <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
            <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
            <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
        </tr>
        {% endfor %}
    </table>

    {% set delivery = order.deliveries.first %}
    {% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
    {% set total = order.price.totalPrice %}

    {% if displayRounded %}
        {% set total = order.price.rawTotal %}
    {% endif %}
    <p>
        <br>
        <br>
        Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>

        Net total: {{ order.amountNet|currency(currencyIsoCode) }}<br>
        {% for calculatedTax in order.price.calculatedTaxes %}
            {% if order.taxStatus is same as('net') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
        {% endfor %}
        {% if not displayRounded %}<strong>{% endif %}Total gross: {{ total|currency(currencyIsoCode) }}{% if not displayRounded %}<strong>{% endif %}<br>
        {% if displayRounded %}
            <strong>Rounded total gross: {{ order.price.totalPrice|currency(currencyIsoCode) }}<strong><br>
        {% endif %}
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
        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
        </br>
        If you have any questions, do not hesitate to contact us.

    </p>
    <br>
    </div>
EOF;
    }

    private function getOrderConfirmationHTMLTemplateDe(): string
    {
        return <<<'EOF'
<div style="font-family:arial; font-size:12px;">

{% set currencyIsoCode = order.currency.isoCode %}

Hallo {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
<br>
Ihre Bestellung ist am {{ order.orderDateTime|date }} bei uns eingegangen.<br>
<br>
Bestellnummer: {{ order.orderNumber }}<br>
<br>
Sobald ein Zahlungseingang erfolgt ist, erhalten Sie eine separate Benachrichtigung und Ihre Bestellung wird verarbeitet.<br>
<br>
Den aktuellen Status Ihrer Bestellung können Sie jederzeit über diesen Link abrufen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}<br>
Über diesen Link können Sie auch die Bestellung bearbeiten, die Zahlungsart wechseln oder nachträglich eine Zahlung durchführen.<br>
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
            {% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}
                {% for option in lineItem.payload.options %}
                    {{ option.group }}: {{ option.option }}
                    {% if lineItem.payload.options|last != option %}
                        {{ " | " }}
                    {% endif %}
                {% endfor %}
                <br/>
            {% endif %}
          {% if lineItem.payload.productNumber is defined %}Artikel-Nr: {{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
        </td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
    </tr>
    {% endfor %}
</table>

{% set delivery = order.deliveries.first %}
{% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
<p>
    <br>
    <br>
    Versandkosten: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
    Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}<br>
        {% for calculatedTax in order.price.calculatedTaxes %}
            {% if order.taxStatus is same as('net') %}zzgl.{% else %}inkl.{% endif %} {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
        {% endfor %}
    <strong>Gesamtkosten{% if displayRounded %} gerundet{% endif %} Brutto: {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
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
    Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
    </br>
    Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.
</p>
<br>
</div>
EOF;
    }

    private function getOrderConfirmationPlainTemplateDe(): string
    {
        return <<<'EOF'
{% set currencyIsoCode = order.currency.isoCode %}
Hallo {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

Ihre Bestellung ist am {{ order.orderDateTime|date }} bei uns eingegangen.

Bestellnummer: {{ order.orderNumber }}

Sobald ein Zahlungseingang erfolgt ist, erhalten Sie eine separate Benachrichtigung und Ihre Bestellung wird verarbeitet.

Den aktuellen Status Ihrer Bestellung können Sie jederzeit über diesen Link abrufen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
Über diesen Link können Sie auch die Bestellung bearbeiten, die Zahlungsart wechseln oder nachträglich eine Zahlung durchführen.

Informationen zu Ihrer Bestellung:

Pos.   Artikel-Nr.			Beschreibung			Menge			Preis			Summe
{% for lineItem in order.lineItems %}
{{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}				{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery = order.deliveries.first %}
{% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}

Versandkosten: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}
    {% for calculatedTax in order.price.calculatedTaxes %}
        {% if order.taxStatus is same as('net') %}zzgl.{% else %}inkl.{% endif %} {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}
    {% endfor %}
Gesamtkosten{% if displayRounded %} gerundet{% endif %} Brutto: {{ order.amountTotal|currency(currencyIsoCode) }}

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

Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.
EOF;
    }

    private function getOrderConfirmationHTMLTemplateEn(): string
    {
        return <<<'EOF'
<div style="font-family:arial; font-size:12px;">

{% set currencyIsoCode = order.currency.isoCode %}
{{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
<br>
We have received your order from {{ order.orderDateTime|date }}.<br>
<br>
Order number: {{ order.orderNumber }}<br>
<br>
As soon as your payment has been made, you will receive a separate notification and your order will be processed.<br>
<br>
You may check the current status of your order with this link: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}<br>
You may use this link to edit your order, change the payment method or make additional payments.<br>
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
            {% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}
                {% for option in lineItem.payload.options %}
                    {{ option.group }}: {{ option.option }}
                    {% if lineItem.payload.options|last != option %}
                        {{ " | " }}
                    {% endif %}
                {% endfor %}
                <br/>
            {% endif %}
          {% if lineItem.payload.productNumber is defined %}Prod. No.: {{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
        </td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
    </tr>
    {% endfor %}
</table>

{% set delivery = order.deliveries.first %}
{% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
{% set total = order.price.totalPrice %}

{% if displayRounded %}
    {% set total = order.price.rawTotal %}
{% endif %}
<p>
    <br>
    <br>
    Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>

    Net total: {{ order.amountNet|currency(currencyIsoCode) }}<br>
    {% for calculatedTax in order.price.calculatedTaxes %}
        {% if order.taxStatus is same as('net') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
    {% endfor %}
    {% if not displayRounded %}<strong>{% endif %}Total gross: {{ total|currency(currencyIsoCode) }}{% if not displayRounded %}<strong>{% endif %}<br>
    {% if displayRounded %}
        <strong>Rounded total gross: {{ order.price.totalPrice|currency(currencyIsoCode) }}<strong><br>
    {% endif %}
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
    You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
    </br>
    If you have any questions, do not hesitate to contact us.

</p>
<br>
</div>
EOF;
    }

    private function getOrderConfirmationPlainTemplateEn(): string
    {
        return <<<'EOF'
{% set currencyIsoCode = order.currency.isoCode %}
{{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

We have received your order from {{ order.orderDateTime|date }}.

Order number: {{ order.orderNumber }}

As soon as your payment has been made, you will receive a separate notification and your order will be processed.

You may check the current status of your order with this link: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
You may use this link to edit your order, change the payment method or make additional payments.

Information on your order:

Pos.   Prod. No.			Description			Quantities			Price			Total
{% for lineItem in order.lineItems %}
{{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}				{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery = order.deliveries.first %}
{% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
{% set total = order.price.totalPrice %}

{% if displayRounded %}
    {% set total = order.price.rawTotal %}
{% endif %}

Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
Net total: {{ order.amountNet|currency(currencyIsoCode) }}
    {% for calculatedTax in order.price.calculatedTaxes %}
           {% if order.taxStatus is same as('net') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}
    {% endfor %}
Total gross: {{ total|currency(currencyIsoCode) }}
{% if displayRounded %}
Rounded total gross: {{ order.price.totalPrice|currency(currencyIsoCode) }}
{% endif %}

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

You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode}, salesChannel.domains|first.url) }}
If you have any questions, do not hesitate to contact us.

However, in case you have purchased without a registration or a customer account, you do not have this option.
EOF;
    }
}
