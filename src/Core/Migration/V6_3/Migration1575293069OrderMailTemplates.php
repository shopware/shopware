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
class Migration1575293069OrderMailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1575293069;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $enLangId = $this->fetchLanguageId('en-GB', $connection);
        $deLangId = $this->fetchLanguageId('de-DE', $connection);

        // update order confirmation
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);
        if ($templateId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $this->getOrderConfirmationHtmlTemplateEn(),
                $this->getOrderConfirmationPlainTemplateEn()
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $deLangId,
                $this->getOrderConfirmationHtmlTemplateDe(),
                $this->getOrderConfirmationPlainTemplateDe()
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
            'enLangId' => $langId,
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

        $sqlString = 'UPDATE `mail_template_translation` SET ' . $sqlString . 'WHERE `mail_template_id`= :templateId AND `language_id` = :enLangId AND `updated_at` IS NULL';

        $connection->executeStatement($sqlString, $sqlParams);
    }

    private function getOrderConfirmationHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">

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
          Art. No.: {{ lineItem.payload.productNumber|u.wordwrap(80) }}
        </td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
    </tr>
    {% endfor %}
</table>

{% set delivery =order.deliveries.first %}
<p>
    <br>
    <br>
    Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
    Net total: {{ order.amountNet|currency(currencyIsoCode) }}<br>
    {% if order.taxStatus is same as(\'net\') %}
        {% for calculatedTax in order.cartPrice.calculatedTaxes %}
            plus {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
        {% endfor %}
        <strong>Total gross: {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
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

    If you have any questions, do not hesitate to contact us.

</p>
<br>
</div>';
    }

    private function getOrderConfirmationPlainTemplateEn(): string
    {
        return '{% set currencyIsoCode = order.currency.isoCode %}
{{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

Thank you for your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.

Information on your order:

Pos.   Art.No.			Description			Quantities			Price			Total

{% for lineItem in order.lineItems %}
{{ loop.index }}      {{ lineItem.payload.productNumber|u.wordwrap(80) }}				{{ lineItem.label|u.wordwrap(80) }}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery =order.deliveries.first %}

Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
Net total: {{ order.amountNet|currency(currencyIsoCode) }}
{% if order.taxStatus is same as(\'net\') %}
	{% for calculatedTax in order.cartPrice.calculatedTaxes %}
		plus {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}
	{% endfor %}
	Total gross: {{ order.amountTotal|currency(currencyIsoCode) }}
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

If you have any questions, do not hesitate to contact us.

';
    }

    private function getOrderConfirmationHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">

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
          Artikel-Nr: {{ lineItem.payload.productNumber|u.wordwrap(80) }}
        </td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
    </tr>
    {% endfor %}
</table>

{% set delivery =order.deliveries.first %}
<p>
    <br>
    <br>
    Versandkosten: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
    Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}<br>
    {% if order.taxStatus is same as(\'net\') %}
        {% for calculatedTax in order.cartPrice.calculatedTaxes %}
            zzgl. {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
        {% endfor %}
        <strong>Gesamtkosten Brutto: {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
    {% endif %}
    <br>

    <strong>Gewählte Zahlungsart:</strong> {{ order.transactions.first.paymentMethod.name }}<br>
    {{ order.transactions.first.paymentMethod.description }}<br>
    <br>

    <strong>Gewählte Versandtart:</strong> {{ delivery.shippingMethod.name }}<br>
    {{ delivery.shippingMethod.description }}<br>
    <br>

    {% set billingAddress = order.addresses.get(order.billingAddressId) %}
    <strong>Rechnungsaddresse:</strong><br>
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

    Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.

</p>
<br>
</div>';
    }

    private function getOrderConfirmationPlainTemplateDe(): string
    {
        return '{% set currencyIsoCode = order.currency.isoCode %}
Hallo {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

vielen Dank für Ihre Bestellung im {{ salesChannel.name }} (Nummer: {{order.orderNumber}}) am {{ order.orderDateTime|date }}.

Informationen zu Ihrer Bestellung:

Pos.   Artikel-Nr.			Beschreibung			Menge			Preis			Summe
{% for lineItem in order.lineItems %}
{{ loop.index }}     {{ lineItem.payload.productNumber|u.wordwrap(80) }}				{{ lineItem.label|u.wordwrap(80) }}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery =order.deliveries.first %}

Versandtkosten: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}
{% if order.taxStatus is same as(\'net\') %}
	{% for calculatedTax in order.cartPrice.calculatedTaxes %}
		zzgl. {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}
	{% endfor %}
	Gesamtkosten Brutto: {{ order.amountTotal|currency(currencyIsoCode) }}
{% endif %}

Gewählte Zahlungsart: {{ order.transactions.first.paymentMethod.name }}
{{ order.transactions.first.paymentMethod.description }}

Gewählte Versandtart: {{ delivery.shippingMethod.name }}
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

Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.

';
    }
}
