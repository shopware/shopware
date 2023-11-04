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
class Migration1570621541UpdateDefaultMailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570621541;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $defaultLangId = $this->fetchLanguageId('en-GB', $connection);
        $deLangId = $this->fetchLanguageId('de-DE', $connection);

        // update order confirmation
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);
        if ($templateId !== null) {
            if ($defaultLangId !== $deLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $defaultLangId,
                    $this->getOrderConfirmationHtmlTemplateEn(),
                    $this->getOrderConfirmationPlainTemplateEn()
                );
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    $this->getOrderConfirmationHtmlTemplateEn(),
                    $this->getOrderConfirmationPlainTemplateEn()
                );
            }

            if ($deLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $deLangId,
                    $this->getOrderConfirmationHtmlTemplateDe(),
                    $this->getOrderConfirmationPlainTemplateDe()
                );
            }
        }

        // update customer registration
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER);
        if ($templateId !== null) {
            if ($defaultLangId !== $deLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $defaultLangId,
                    $this->getRegistrationHtmlTemplateEn(),
                    $this->getRegistrationPlainTemplateEn()
                );
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    $this->getRegistrationHtmlTemplateEn(),
                    $this->getRegistrationPlainTemplateEn()
                );
            }

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $deLangId,
                $this->getRegistrationHtmlTemplateDe(),
                $this->getRegistrationPlainTemplateDe()
            );
        }

        // update password change
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_PASSWORD_CHANGE);
        if ($templateId !== null) {
            if ($defaultLangId !== $deLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $defaultLangId,
                    $this->getPasswordChangeHtmlTemplateEn(),
                    $this->getPasswordChangePlainTemplateEn()
                );
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    $this->getPasswordChangeHtmlTemplateEn(),
                    $this->getPasswordChangePlainTemplateEn()
                );
            }

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $deLangId,
                $this->getPasswordChangeHtmlTemplateDe(),
                $this->getPasswordChangePlainTemplateDe()
            );
        }

        // update newsletter register
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, 'newsletterRegister');
        if ($templateId !== null) {
            if ($defaultLangId !== $deLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $defaultLangId,
                    $this->getRegisterTemplate_HTML_EN(),
                    $this->getRegisterTemplate_PLAIN_EN(),
                    '{{ salesChannel.translated.name }}'
                );
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    $this->getRegisterTemplate_HTML_EN(),
                    $this->getRegisterTemplate_PLAIN_EN(),
                    '{{ salesChannel.translated.name }}'
                );
            }

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $deLangId,
                $this->getRegisterTemplate_HTML_DE(),
                $this->getRegisterTemplate_PLAIN_DE(),
                '{{ salesChannel.translated.name }}'
            );
        }

        // update newsletter opt in
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, 'newsletterDoubleOptIn');
        if ($templateId !== null) {
            if ($defaultLangId !== $deLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $defaultLangId,
                    $this->getOptInTemplate_HTML_EN(),
                    $this->getOptInTemplate_PLAIN_EN(),
                    '{{ salesChannel.translated.name }}'
                );
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    $this->getOptInTemplate_HTML_EN(),
                    $this->getOptInTemplate_PLAIN_EN(),
                    '{{ salesChannel.translated.name }}'
                );
            }

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $deLangId,
                $this->getOptInTemplate_HTML_DE(),
                $this->getOptInTemplate_PLAIN_DE(),
                '{{ salesChannel.translated.name }}'
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
        $langId = $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]);

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
{{order.orderCustomer.salutation.translated.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
<br>
Thank you for your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.<br>
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
          {{ lineItem.label|wordwrap(80) }}<br>
          Art. No.: {{ lineItem.payload.productNumber|wordwrap(80) }}
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
{{order.orderCustomer.salutation.translated.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

Thank you for your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.

Information on your order:

Pos.   Art.No.			Description			Quantities			Price			Total

{% for lineItem in order.lineItems %}
{{ loop.index }}      {{ lineItem.payload.productNumber|wordwrap(80) }}				{{ lineItem.label|wordwrap(80) }}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
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
Hallo {{order.orderCustomer.salutation.translated.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
<br>
vielen Dank für Ihre Bestellung im {{ salesChannel.translated.name }} (Nummer: {{order.orderNumber}}) am {{ order.orderDateTime|date }}.<br>
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
          {{ lineItem.label|wordwrap(80) }}<br>
          Artikel-Nr: {{ lineItem.payload.productNumber|wordwrap(80) }}
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
Hallo {{order.orderCustomer.salutation.translated.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

vielen Dank für Ihre Bestellung im {{ salesChannel.translated.name }} (Nummer: {{order.orderNumber}}) am {{ order.orderDateTime|date }}.

Informationen zu Ihrer Bestellung:

Pos.   Artikel-Nr.			Beschreibung			Menge			Preis			Summe
{% for lineItem in order.lineItems %}
{{ loop.index }}     {{ lineItem.payload.productNumber|wordwrap(80) }}				{{ lineItem.label|wordwrap(80) }}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
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

    private function getRegistrationHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                {{ customer.salutation.translated.letterName }} {{ customer.firstName }} {{ customer.lastName }},<br/>
                <br/>
                thank you for your signing up with our Shop.<br/>
                You will gain access via the email address <strong>{{ customer.email }}</strong> and the password you have chosen.<br/>
                You can change your password anytime.
            </p>
        </div>';
    }

    private function getRegistrationPlainTemplateEn(): string
    {
        return '{{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }},

                thank you for your signing up with our Shop.
                You will gain access via the email address {{ customer.email }} and the password you have chosen.
                You can change your password anytime.
        ';
    }

    private function getRegistrationHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                Hallo {{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }},<br/>
                <br/>
                vielen Dank für Ihre Anmeldung in unserem Shop.<br/>
                Sie erhalten Zugriff über Ihre E-Mail-Adresse <strong>{{ customer.email }}</strong> und dem von Ihnen gewählten Kennwort.<br/>
                Sie können Ihr Kennwort jederzeit nachträglich ändern.
            </p>
        </div>';
    }

    private function getRegistrationPlainTemplateDe(): string
    {
        return 'Hallo {{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }},

                vielen Dank für Ihre Anmeldung in unserem Shop.
                Sie erhalten Zugriff über Ihre E-Mail-Adresse {{ customer.email }} und dem von Ihnen gewählten Kennwort.
                Sie können Ihr Kennwort jederzeit nachträglich ändern.
';
    }

    private function getPasswordChangeHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        {{ customer.salutation.translated.letterName }} {{ customer.firstName }} {{ customer.lastName }},<br/>
        <br/>
        there has been a request to reset you Password in the Shop {{ salesChannel.translated.name }}
        Please confirm the link below to specify a new password.<br/>
        <br/>
        <a href="{{ urlResetPassword }}">Reset passwort</a><br/>
        <br/>
        This link is valid for the next 2 hours. After that you have to request a new confirmation link.<br/>
        If you do not want to reset your password, please ignore this email. No changes will be made.
    </p>
</div>';
    }

    private function getPasswordChangePlainTemplateEn(): string
    {
        return '
        {{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }},

        there has been a request to reset you Password in the Shop {{ salesChannel.translated.name }}
        Please confirm the link below to specify a new password.

        Reset password: {{ urlResetPassword }}

        This link is valid for the next 2 hours. After that you have to request a new confirmation link.
        If you do not want to reset your password, please ignore this email. No changes will be made.
    ';
    }

    private function getPasswordChangeHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Hallo {{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }},<br/>
        <br/>
        im Shop {{ salesChannel.translated.name }} wurde eine Anfrage gestellt, um Ihr Passwort zurück zu setzen.
        Bitte bestätigen Sie den unten stehenden Link, um ein neues Passwort zu definieren.<br/>
        <br/>
        <a href="{{ urlResetPassword }}">Passwort zurücksetzen</a><br/>
        <br/>
        Dieser Link ist nur für die nächsten 2 Stunden gültig. Danach muss das Zurücksetzen des Passwortes erneut beantragt werden.
        Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.
    </p>
</div>';
    }

    private function getPasswordChangePlainTemplateDe(): string
    {
        return '
        Hallo {{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }},

        im Shop {{ salesChannel.translated.name }} wurde eine Anfrage gestellt, um Ihr Passwort zurück zu setzen.
        Bitte bestätigen Sie den unten stehenden Link, um ein neues Passwort zu definieren.

        Passwort zurücksetzen: {{ urlResetPassword }}

        Dieser Link ist nur für die nächsten 2 Stunden gültig. Danach muss das Zurücksetzen des Passwortes erneut beantragt werden.
        Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.
';
    }

    private function getRegisterTemplate_HTML_EN(): string
    {
        return '<h3>Hello {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}</h3>
                <p>thank you very much for your registration.</p>
                <p>You have successfully subscribed to our newsletter.</p>
        ';
    }

    private function getRegisterTemplate_PLAIN_EN(): string
    {
        return 'Hello {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}

                thank you very much for your registration.

                You have successfully subscribed to our newsletter.
        ';
    }

    private function getRegisterTemplate_HTML_DE(): string
    {
        return '<h3>Hallo {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}</h3>
                <p>vielen Dank für Ihre Anmeldung.</p>
                <p>Sie haben sich erfolgreich zu unserem Newsletter angemeldet.</p>
        ';
    }

    private function getRegisterTemplate_PLAIN_DE(): string
    {
        return 'Hallo {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}

                vielen Dank für Ihre Anmeldung.

                Sie haben sich erfolgreich zu unserem Newsletter angemeldet.
        ';
    }

    private function getOptInTemplate_HTML_EN(): string
    {
        return '<h3>Hello {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}</h3>
                <p>Thank you for your interest in our newsletter!</p>
                <p>In order to prevent misuse of your email address, we have sent you this confirmation email. Confirm that you wish to receive the newsletter regularly by clicking <a href="{{ url }}">here</a>.</p>
                <p>If you have not subscribed to the newsletter, please ignore this email.</p>
        ';
    }

    private function getOptInTemplate_PLAIN_EN(): string
    {
        return 'Hello {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}

                Thank you for your interest in our newsletter!

                In order to prevent misuse of your email address, we have sent you this confirmation email. Confirm that you wish to receive the newsletter regularly by clicking on the link: {{ url }}

                If you have not subscribed to the newsletter, please ignore this email.
        ';
    }

    private function getOptInTemplate_HTML_DE(): string
    {
        return '<h3>Hallo {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}</h3>
                <p>Schön, dass Sie sich für unseren Newsletter interessieren!</p>
                <p>Um einem Missbrauch Ihrer E-Mail-Adresse vorzubeugen, haben wir Ihnen diese Bestätigungsmail gesendet. Bestätigen Sie, dass Sie den Newsletter regelmäßig erhalten wollen, indem Sie <a href="{{ url }}">hier</a> klicken.</p>
                <p>Sollten Sie den Newsletter nicht angefordert haben, ignorieren Sie diese E-Mail.</p>
        ';
    }

    private function getOptInTemplate_PLAIN_DE(): string
    {
        return 'Hallo {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}

                Schön, dass Sie sich für unseren Newsletter interessieren!

                Um einem Missbrauch Ihrer E-Mail-Adresse vorzubeugen, haben wir Ihnen diese Bestätigungsmail gesendet. Bestätigen Sie, dass Sie den Newsletter regelmäßig erhalten wollen, indem Sie auf den folgenden Link klicken: {{ url }}

                Sollten Sie den Newsletter nicht angefordert haben, ignorieren Sie diese E-Mail.
        ';
    }
}
