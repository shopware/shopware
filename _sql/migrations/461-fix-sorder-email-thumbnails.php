<?php

class Migrations_Migration461 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus === self::MODUS_INSTALL) {
            $this->updateTemplates();
            $this->updateTranslations();
        }
    }

    /**
     * Update all mail templates
     */
    private function updateTemplates()
    {
        $this->updateTemplate(
            'sORDER',
            '{include file=\"string:{config name=emailheaderhtml}\"}\r\n<br/><br/>\r\n<p>\nHallo {$billingaddress.firstname} {$billingaddress.lastname},<br/><br/>\n\nvielen Dank fuer Ihre Bestellung bei {config name=shopName} (Nummer: {$sOrderNumber}) am {$sOrderDay|date:\"DATE_MEDIUM\"} um {$sOrderTime|date:\"TIME_SHORT\"}.\n<br/>\n<br/>\n<strong>Informationen zu Ihrer Bestellung:</strong></p>\n  <table width=\"80%\" border=\"0\" style=\"font-family:Arial, Helvetica, sans-serif; font-size:10px;\">\n    <tr>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Artikel</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Pos.</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Art-Nr.</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Menge</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Preis</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Summe</strong></td>\n    </tr>\n\n    {foreach item=details key=position from=$sOrderDetails}\n    <tr>\n      <td rowspan=\"2\" style=\"border-bottom:1px solid #cccccc;\">{if $details.image.src.0}<img style=\"height: 57px;\" src=\"{$details.image.src.0}\" alt=\"{$details.articlename}\" />{else} {/if}</td>\n      <td>{$position+1|fill:4} </td>\n      <td>{$details.ordernumber|fill:20}</td>\n      <td>{$details.quantity|fill:6}</td>\n      <td>{$details.price|padding:8}{$sCurrency}</td>\n      <td>{$details.amount|padding:8} {$sCurrency}</td>\n    </tr>\n    <tr>\n      <td colspan=\"5\" style=\"border-bottom:1px solid #cccccc;\">{$details.articlename|wordwrap:80|indent:4}</td>\n    </tr>\n    {/foreach}\n\n  </table>\n\n<p>\n  <br/>\n  <br/>\n    Versandkosten: {$sShippingCosts}<br/>\n    Gesamtkosten Netto: {$sAmountNet}<br/>\n    {if !$sNet}\n    Gesamtkosten Brutto: {$sAmount}<br/>\n    {/if}\n  <br/>\n  <br/>\n    <strong>Gewählte Zahlungsart:</strong> {$additional.payment.description}<br/>\n    {$additional.payment.additionaldescription}\n    {if $additional.payment.name == \"debit\"}\n    Ihre Bankverbindung:<br/>\n    Kontonr: {$sPaymentTable.account}<br/>\n    BLZ:{$sPaymentTable.bankcode}<br/>\n    Wir ziehen den Betrag in den nächsten Tagen von Ihrem Konto ein.<br/>\n    {/if}\n  <br/>\n  <br/>\n    {if $additional.payment.name == \"prepayment\"}\n    Unsere Bankverbindung:<br/>\n    {config name=bankAccount}\n    {/if}\n  <br/>\n  <br/>\n    <strong>Gewählte Versandart:</strong> {$sDispatch.name}<br/>{$sDispatch.description}\n</p>\n<p>\n  {if $sComment}\n    <strong>Ihr Kommentar:</strong><br/>\n    {$sComment}<br/>\n  {/if}\n  <br/>\n  <br/>\n    <strong>Rechnungsadresse:</strong><br/>\n    {$billingaddress.company}<br/>\n    {$billingaddress.firstname} {$billingaddress.lastname}<br/>\n    {$billingaddress.street}<br/>\n    {$billingaddress.zipcode} {$billingaddress.city}<br/>\n    {$billingaddress.phone}<br/>\n    {$additional.country.countryname}<br/>\n  <br/>\n  <br/>\n    <strong>Lieferadresse:</strong><br/>\n    {$shippingaddress.company}<br/>\n    {$shippingaddress.firstname} {$shippingaddress.lastname}<br/>\n    {$shippingaddress.street}<br/>\n    {$shippingaddress.zipcode} {$shippingaddress.city}<br/>\n    {$additional.countryShipping.countryname}<br/>\n  <br/>\n    {if $billingaddress.ustid}\n    Ihre Umsatzsteuer-ID: {$billingaddress.ustid}<br/>\n    Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland<br/>\n    bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit.<br/>\n    {/if}\n  <br/>\n  <br/>\n    Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung. Sie erreichen uns wie folgt: <br/>{config name=address}\n</p>\r\n<br/><br/>\r\n{include file=\"string:{config name=emailfooterhtml}\"}'
        );
    }

    /**
     * Update a mail template
     *
     * @param string $name
     * @param string $content
     */
    private function updateTemplate($name, $content = '')
    {
        $sql = <<<SQL
UPDATE `s_core_config_mails` SET `contentHTML` = "$content" WHERE `name` = "$name" AND dirty = 0
SQL;
        $this->addSql($sql);
    }

    /**
     * Update translations of mail templates
     */
    private function updateTranslations()
    {
        $this->updateTranslation(
            'sORDER',
            "{include file=\"string:{config name=emailheaderhtml}\"}\r\n<br/><br/>\r\n<p>Hello {\$billingaddress.firstname} {\$billingaddress.lastname},<br/><br/>\n\nThank you for your order with {config name=shopName} (Nummer: {\$sOrderNumber}) on {\$sOrderDay} at {\$sOrderTime}.\n<br/>\n<br/>\n<strong>Information on your order:</strong></p>\n  <table width=\"80%\" border=\"0\" style=\"font-family:Arial, Helvetica, sans-serif; font-size:10px;\">\n    <tr>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Art.No.</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Pos.</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Art-Nr.</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Quantities</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Price</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Total</strong></td>\n    </tr>\n\n    {foreach item=details key=position from=\$sOrderDetails}\n    <tr>\n      <td rowspan=\"2\" style=\"border-bottom:1px solid #cccccc;\">{if \$details.image.src.0}<img style=\"height: 57px;\" src=\"{\$details.image.src.0}\" alt=\"{\$details.articlename}\" />{else} {/if}</td>\n      <td>{\$position+1|fill:4} </td>\n      <td>{\$details.ordernumber|fill:20}</td>\n      <td>{\$details.quantity|fill:6}</td>\n      <td>{\$details.price|padding:8}{\$sCurrency}</td>\n      <td>{\$details.amount|padding:8} {\$sCurrency}</td>\n    </tr>\n    <tr>\n      <td colspan=\"5\" style=\"border-bottom:1px solid #cccccc;\">{\$details.articlename|wordwrap:80|indent:4}</td>\n    </tr>\n    {/foreach}\n\n  </table>\n\n<p>\n  <br/>\n  <br/>\n    Shipping costs:: {\$sShippingCosts}<br/>\n    Total net: {\$sAmountNet}<br/>\n    {if !\$sNet}\n    Total gross: {\$sAmount}<br/>\n    {/if}\n  <br/>\n  <br/>\n    <strong>Selected payment type:</strong> {\$additional.payment.description}<br/>\n    {\$additional.payment.additionaldescription}\n    {if \$additional.payment.name == \"debit\"}\n    Your bank connection:<br/>\n    Account number: {\$sPaymentTable.account}<br/>\n    BIN:{\$sPaymentTable.bankcode}<br/>\n    We will withdraw the money from your bank account within the next days.<br/>\n    {/if}\n  <br/>\n  <br/>\n    {if \$additional.payment.name == \"prepayment\"}\n    Our bank connection:<br/>\n    {config name=bankAccount}\n    {/if}\n  <br/>\n  <br/>\n    <strong>Selected dispatch:</strong> {\$sDispatch.name}<br/>{\$sDispatch.description}\n</p>\n<p>\n  {if \$sComment}\n    <strong>Your comment:</strong><br/>\n    {\$sComment}<br/>\n  {/if}\n  <br/>\n  <br/>\n    <strong>Billing address:</strong><br/>\n    {\$billingaddress.company}<br/>\n    {\$billingaddress.firstname} {\$billingaddress.lastname}<br/>\n    {\$billingaddress.street}<br/>\n    {\$billingaddress.zipcode} {\$billingaddress.city}<br/>\n    {\$billingaddress.phone}<br/>\n    {\$additional.country.countryname}<br/>\n  <br/>\n  <br/>\n    <strong>Shipping address:</strong><br/>\n    {\$shippingaddress.company}<br/>\n    {\$shippingaddress.firstname} {\$shippingaddress.lastname}<br/>\n    {\$shippingaddress.street}<br/>\n    {\$shippingaddress.zipcode} {\$shippingaddress.city}<br/>\n    {\$additional.countryShipping.countryname}<br/>\n  <br/>\n    {if \$billingaddress.ustid}\n    Your VAT-ID: {\$billingaddress.ustid}<br/>\n    In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.\n    {/if}</p>\r\n<br/><br/>\r\n{include file=\"string:{config name=emailfooterhtml}\"}"
        );
    }

    /**
     * Helper method to update the translations of a mail template
     * @param string $name
     * @param string $content
     */
    private function updateTranslation($name, $content)
    {
        $sql = <<<SQL
SELECT s_core_translations.id, s_core_translations.objectdata
FROM
    s_core_translations
INNER JOIN
    s_core_config_mails
ON
    s_core_translations.objectkey = s_core_config_mails.id
WHERE
    s_core_translations.objecttype = 'config_mails'
AND
    s_core_config_mails.name = "$name"
SQL;

        $translation = $this->connection->query($sql)->fetch();
        if (!$translation) {
            return;
        }

        $id = $translation['id'];
        $data = unserialize($translation['objectdata']);

        $data['contentHtml'] = stripslashes($content);

        $data = serialize($data);

        $sql = <<<SQL
UPDATE `s_core_translations` SET `objectdata`= '$data' WHERE `id` = $id AND dirty = 0
SQL;

        $this->addSql($sql);
    }
}
