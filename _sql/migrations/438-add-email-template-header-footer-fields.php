<?php

class Migrations_Migration438 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addConfigFields();
        $this->fixContextData();
        $this->updateTemplates();
        $this->updateTranslations();
    }

    /**
     * Add the new configuration fields
     *
     * - emailheaderplain
     * - emailfooterplain
     * - emailheaderhtml
     * - emailfooterhtml
     */
    private function addConfigFields()
    {
        $sql = <<<'SQL'
SET @formId = (SELECT id FROM s_core_config_forms WHERE name = 'Frontend60' LIMIT 1);

INSERT IGNORE INTO `s_core_config_elements` (`form_id`, `name`, `value`, `label`, `type`, `required`, `position`, `scope`, `filters`, `validators`, `options`) VALUES
(@formId, 'emailheaderplain', 's:0:"";', 'E-Mail Header Plaintext', 'textarea', 0, 0, 1, NULL, NULL, NULL),
(@formId, 'emailfooterplain', 's:64:"\nMit freundlichen Grüßen,\n\nIhr Team von {config name=shopName}";', 'E-Mail Footer Plaintext', 'textarea', 0, 0, 1, NULL, NULL, NULL),
(@formId, 'emailheaderhtml', 's:137:"<div>\n<img src=\"{\$sShopURL}/themes/Frontend/Responsive/frontend/_public/src/img/logos/logo--tablet.png\" alt=\"Logo\"><br />";', 'E-Mail Header HTML', 'textarea', 0, 0, 1, NULL, NULL, NULL),
(@formId, 'emailfooterhtml', 's:85:"<br/>\nMit freundlichen Grüßen,<br/><br/>\n\nIhr Team von {config name=shopName}</div>";', 'E-Mail Footer HTML', 'textarea', 0, 0, 1, NULL, NULL, NULL);

SET @emailheaderplainid = (SELECT id FROM s_core_config_elements WHERE name = 'emailheaderplain' LIMIT 1);
SET @emailfooterplainid = (SELECT id FROM s_core_config_elements WHERE name = 'emailfooterplain' LIMIT 1);
SET @emailheaderhtmlid  = (SELECT id FROM s_core_config_elements WHERE name = 'emailheaderhtml' LIMIT 1);
SET @emailfooterhtmlid  = (SELECT id FROM s_core_config_elements WHERE name = 'emailfooterhtml' LIMIT 1);

INSERT IGNORE INTO `s_core_config_element_translations` (`element_id`, `locale_id`, `label`) VALUES
(@emailheaderplainid, '2', 'Email header plaintext'),
(@emailfooterplainid, '2', 'Email footer plaintext'),
(@emailheaderhtmlid, '2', 'Email header HTML'),
(@emailfooterhtmlid, '2', 'Email footer HTML');
SQL;

        $this->addSql($sql);
    }

    /**
     * Fix the contexts of mail templates
     */
    private function fixContextData()
    {
        $sql = <<<SQL
            UPDATE `s_core_config_mails` SET
            `context` = REPLACE (`context`, 's:8:"Banjimen"', 's:3:"Max"'),
            `context` = REPLACE (`context`, 's:6:"Ercmer"', 's:10:"Mustermann"')
            WHERE (
              `context` LIKE '%Banjimen%' OR `context` LIKE '%Ercmer%'
            ) AND dirty = 0;
SQL;

        $this->addSql($sql);
    }

    /**
     * Update a mail template
     *
     * @param string $name
     * @param string $content
     * @param string $contentHtml
     */
    private function updateTemplate($name, $content, $contentHtml = null)
    {
        $content = $this->convertTemplatePlain($content);

        if ($contentHtml) {
            $contentHtml = $this->convertTemplateHtml($contentHtml);
        }

        $this->updateEmailTemplate($name, $content, $contentHtml);
    }

    /**
     * Helper method to update the translations of a mail template
     * @param string $name
     * @param string $content
     * @param string $contentHtml
     */
    private function updateTranslation($name, $content, $contentHtml = null)
    {
        $content = stripslashes($this->convertTemplatePlain($content));

        $contentHtml = stripslashes($this->convertTemplateHtml($contentHtml));

        $this->updateEmailTemplateTranslation($name, $content, $contentHtml);
    }

    /**
     * Updates an email template
     *
     * @param string $name
     * @param string $content
     * @param string $contentHtml
     */
    private function updateEmailTemplate($name, $content, $contentHtml = null)
    {
        $sql = <<<SQL
UPDATE `s_core_config_mails` SET `content` = "$content" WHERE `name` = "$name" AND dirty = 0
SQL;
        $this->addSql($sql);

        if ($contentHtml != null) {
            $sql = <<<SQL
UPDATE `s_core_config_mails` SET `content` = "$content", `contentHTML` = "$contentHtml" WHERE `name` = "$name" AND dirty = 0
SQL;
            $generatedQueries[] = $sql;
        }

        $this->addSql($sql);
    }

    /**
     * Updates an email template's translation
     *
     * @param string $name
     * @param string $content
     * @param string $contentHtml
     */
    private function updateEmailTemplateTranslation($name, $content, $contentHtml = null)
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
AND s_core_translations.dirty = 0
SQL;

        $translation = $this->getConnection()->query($sql)->fetch();

        if (!$translation) {
            return;
        }

        $id = $translation['id'];
        $data = unserialize($translation['objectdata']);

        $data['content'] = $content;

        if ($contentHtml != null) {
            $data['contentHtml'] = $contentHtml;
        }

        $data = serialize($data);

        $sql = <<<SQL
UPDATE `s_core_translations` SET `objectdata`= '$data' WHERE `id` = $id
SQL;

        $this->addSql($sql);
    }

    /**
     * Helper method to prefix and suffix the mail templates with the configuration values
     *
     * @param string $content
     * @return string
     */
    private function convertTemplatePlain($content)
    {
        $header = '{include file=\"string:{config name=emailheaderplain}\"}';
        $footer = '{include file=\"string:{config name=emailfooterplain}\"}';

        return $header."\r\n\r\n".$content."\r\n\r\n".$footer;
    }

    /**
     * Helper method to prefix and suffix the mail templates with the configuration values
     *
     * @param string $content
     * @return string
     */
    private function convertTemplateHtml($content)
    {
        $header = '{include file=\"string:{config name=emailheaderhtml}\"}';
        $footer = '{include file=\"string:{config name=emailfooterhtml}\"}';

        return $header."\r\n<br/><br/>\r\n".$content."\r\n<br/><br/>\r\n".$footer;
    }


    /**
     * Update all mail templates
     */
    private function updateTemplates()
    {
        $this->updateTemplate(
            'sREGISTERCONFIRMATION',
            'Hallo {salutation} {firstname} {lastname},\n\nvielen Dank für Ihre Anmeldung in unserem Shop.\n\nSie erhalten Zugriff über Ihre E-Mail-Adresse {sMAIL}\nund dem von Ihnen gewählten Kennwort.\n\nSie können sich Ihr Kennwort jederzeit per E-Mail erneut zuschicken lassen.',
            '<p>\nHallo {salutation} {firstname} {lastname},<br/><br/>\n\nvielen Dank für Ihre Anmeldung in unserem Shop.<br/><br/>\n\nSie erhalten Zugriff über Ihre eMail-Adresse <strong>{sMAIL}</strong><br/>\nund dem von Ihnen gewählten Kennwort.<br/><br/>\n\nSie können sich Ihr Kennwort jederzeit per eMail erneut zuschicken lassen.\n</p>'
        );

        $this->updateTemplate(
            'sORDER',
            'Hallo {$billingaddress.firstname} {$billingaddress.lastname},\n\nvielen Dank fuer Ihre Bestellung bei {config name=shopName} (Nummer: {$sOrderNumber}) am {$sOrderDay|date:\"DATE_MEDIUM\"} um {$sOrderTime|date:\"TIME_SHORT\"}.\nInformationen zu Ihrer Bestellung:\n\nPos. Art.Nr.              Menge         Preis        Summe\n{foreach item=details key=position from=$sOrderDetails}\n{$position+1|fill:4} {$details.ordernumber|fill:20} {$details.quantity|fill:6} {$details.price|padding:8} EUR {$details.amount|padding:8} EUR\n{$details.articlename|wordwrap:49|indent:5}\n{/foreach}\n\nVersandkosten: {$sShippingCosts}\nGesamtkosten Netto: {$sAmountNet}\n{if !$sNet}\nGesamtkosten Brutto: {$sAmount}\n{/if}\n\nGewählte Zahlungsart: {$additional.payment.description}\n{$additional.payment.additionaldescription}\n{if $additional.payment.name == \"debit\"}\nIhre Bankverbindung:\nKontonr: {$sPaymentTable.account}\nBLZ:{$sPaymentTable.bankcode}\nWir ziehen den Betrag in den nächsten Tagen von Ihrem Konto ein.\n{/if}\n{if $additional.payment.name == \"prepayment\"}\n\nUnsere Bankverbindung:\n{config name=bankAccount}\n{/if}\n\n{if $sComment}\nIhr Kommentar:\n{$sComment}\n{/if}\n\nRechnungsadresse:\n{$billingaddress.company}\n{$billingaddress.firstname} {$billingaddress.lastname}\n{$billingaddress.street}\n{$billingaddress.zipcode} {$billingaddress.city}\n{$billingaddress.phone}\n{$additional.country.countryname}\n\nLieferadresse:\n{$shippingaddress.company}\n{$shippingaddress.firstname} {$shippingaddress.lastname}\n{$shippingaddress.street}\n{$shippingaddress.zipcode} {$shippingaddress.city}\n{$additional.country.countryname}\n\n{if $billingaddress.ustid}\nIhre Umsatzsteuer-ID: {$billingaddress.ustid}\nBei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland\nbestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit.\n{/if}\n\n\nFür Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.\n\nWir wünschen Ihnen noch einen schönen Tag.',
            '<p>\nHallo {$billingaddress.firstname} {$billingaddress.lastname},<br/><br/>\n\nvielen Dank fuer Ihre Bestellung bei {config name=shopName} (Nummer: {$sOrderNumber}) am {$sOrderDay|date:\"DATE_MEDIUM\"} um {$sOrderTime|date:\"TIME_SHORT\"}.\n<br/>\n<br/>\n<strong>Informationen zu Ihrer Bestellung:</strong></p>\n  <table width=\"80%\" border=\"0\" style=\"font-family:Arial, Helvetica, sans-serif; font-size:10px;\">\n    <tr>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Artikel</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Pos.</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Art-Nr.</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Menge</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Preis</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Summe</strong></td>\n    </tr>\n\n    {foreach item=details key=position from=$sOrderDetails}\n    <tr>\n      <td rowspan=\"2\" style=\"border-bottom:1px solid #cccccc;\">{if $details.image.src.1}<img src=\"{$details.image.src.1}\" alt=\"{$details.articlename}\" />{else} {/if}</td>\n      <td>{$position+1|fill:4} </td>\n      <td>{$details.ordernumber|fill:20}</td>\n      <td>{$details.quantity|fill:6}</td>\n      <td>{$details.price|padding:8}{$sCurrency}</td>\n      <td>{$details.amount|padding:8} {$sCurrency}</td>\n    </tr>\n    <tr>\n      <td colspan=\"5\" style=\"border-bottom:1px solid #cccccc;\">{$details.articlename|wordwrap:80|indent:4}</td>\n    </tr>\n    {/foreach}\n\n  </table>\n\n<p>\n  <br/>\n  <br/>\n    Versandkosten: {$sShippingCosts}<br/>\n    Gesamtkosten Netto: {$sAmountNet}<br/>\n    {if !$sNet}\n    Gesamtkosten Brutto: {$sAmount}<br/>\n    {/if}\n  <br/>\n  <br/>\n    <strong>Gewählte Zahlungsart:</strong> {$additional.payment.description}<br/>\n    {$additional.payment.additionaldescription}\n    {if $additional.payment.name == \"debit\"}\n    Ihre Bankverbindung:<br/>\n    Kontonr: {$sPaymentTable.account}<br/>\n    BLZ:{$sPaymentTable.bankcode}<br/>\n    Wir ziehen den Betrag in den nächsten Tagen von Ihrem Konto ein.<br/>\n    {/if}\n  <br/>\n  <br/>\n    {if $additional.payment.name == \"prepayment\"}\n    Unsere Bankverbindung:<br/>\n    {config name=bankAccount}\n    {/if}\n  <br/>\n  <br/>\n    <strong>Gewählte Versandart:</strong> {$sDispatch.name}<br/>{$sDispatch.description}\n</p>\n<p>\n  {if $sComment}\n    <strong>Ihr Kommentar:</strong><br/>\n    {$sComment}<br/>\n  {/if}\n  <br/>\n  <br/>\n    <strong>Rechnungsadresse:</strong><br/>\n    {$billingaddress.company}<br/>\n    {$billingaddress.firstname} {$billingaddress.lastname}<br/>\n    {$billingaddress.street}<br/>\n    {$billingaddress.zipcode} {$billingaddress.city}<br/>\n    {$billingaddress.phone}<br/>\n    {$additional.country.countryname}<br/>\n  <br/>\n  <br/>\n    <strong>Lieferadresse:</strong><br/>\n    {$shippingaddress.company}<br/>\n    {$shippingaddress.firstname} {$shippingaddress.lastname}<br/>\n    {$shippingaddress.street}<br/>\n    {$shippingaddress.zipcode} {$shippingaddress.city}<br/>\n    {$additional.countryShipping.countryname}<br/>\n  <br/>\n    {if $billingaddress.ustid}\n    Ihre Umsatzsteuer-ID: {$billingaddress.ustid}<br/>\n    Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland<br/>\n    bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit.<br/>\n    {/if}\n  <br/>\n  <br/>\n    Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung. Sie erreichen uns wie folgt: <br/>{config name=address}\n</p>'
        );

        $this->updateTemplate(
            'sTELLAFRIEND',
            'Hallo,\r\n\r\n{sName} hat für Sie bei {sShop} ein interessantes Produkt gefunden, dass Sie sich anschauen sollten:\r\n\r\n{sArticle}\r\n{sLink}\r\n\r\n{sComment}'
        );

        $this->updateTemplate(
            'sPASSWORD',
            'Hallo,\n\nIhre Zugangsdaten zu {sShopURL} lauten wie folgt:\nBenutzer: {sMail}\nPasswort: {sPassword}'
        );

        $this->updateTemplate(
            'sNOSERIALS',
            'Hallo,\r\n\r\nes sind keine weiteren freien Seriennummern für den Artikel {sArticleName} verfügbar. Bitte stellen Sie umgehend neue Seriennummern ein oder deaktivieren Sie den Artikel.'
        );

        $this->updateTemplate(
            'sVOUCHER',
            'Hallo {customer},\n\n{user} ist Ihrer Empfehlung gefolgt und hat so eben bei {sShop} bestellt.\nWir schenken Ihnen deshalb einen X € Gutschein, den Sie bei Ihrer nächsten Bestellung einlösen können.\n\nIhr Gutschein-Code lautet: XXX'
        );

        $this->updateTemplate(
            'sCUSTOMERGROUPHACCEPTED',
            'Hallo,\n\nIhr Händleraccount bei {$sShop} wurde freigeschaltet.\n\nAb sofort kaufen Sie zum Netto-EK bei uns ein.'
        );

        $this->updateTemplate(
            'sCUSTOMERGROUPHREJECTED',
            'Sehr geehrter Kunde,\n\nvielen Dank für Ihr Interesse an unseren Fachhandelspreisen. Leider liegt uns aber noch kein Gewerbenachweis vor bzw. leider können wir Sie nicht als Fachhändler anerkennen.\n\nBei Rückfragen aller Art können Sie uns gerne telefonisch, per Fax oder per Mail diesbezüglich erreichen.'
        );

        $this->updateTemplate(
            'sORDERSTATEMAIL1',
            'Sehr geehrte{if $sUser.billing_salutation eq \"mr\"}r Herr{elseif $sUser.billing_salutation eq \"ms\"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\r\n\r\nDer Status Ihrer Bestellung mit der Bestellnummer: {$sOrder.ordernumber} vom {$sOrder.ordertime|date_format:\" %d-%m-%Y\"} hat sich geändert. Der neue Status lautet nun {$sOrder.status_description}.'
        );

        $this->updateTemplate(
            'sORDERSTATEMAIL2',
            'Sehr geehrte{if $sUser.billing_salutation eq \"mr\"}r Herr{elseif $sUser.billing_salutation eq \"ms\"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\r\n\r\nDer Status Ihrer Bestellung mit der Bestellnummer: {$sOrder.ordernumber} vom {$sOrder.ordertime|date_format:\" %d-%m-%Y\"} hat sich geändert. Der neue Status lautet nun {$sOrder.status_description}.'
        );

        $this->updateTemplate(
            'sCANCELEDQUESTION',
            'Lieber Kunde,\r\n \r\nSie haben vor kurzem Ihre Bestellung auf {sShop} nicht bis zum Ende durchgeführt - wir sind stets bemüht unseren Kunden das Einkaufen in unserem Shop so angenehm wie möglich zu machen und würden deshalb gerne wissen, woran Ihr Einkauf bei uns gescheitert ist.\r\n \r\nBitte lassen Sie uns doch den Grund für Ihren Bestellabbruch zukommen, Ihren Aufwand entschädigen wir Ihnen in jedem Fall mit einem 5,00 € Gutschein.\r\n \r\nVielen Dank für Ihre Unterstützung.'
        );

        $this->updateTemplate(
            'sCANCELEDVOUCHER',
            'Lieber Kunde,\r\n \r\nSie haben vor kurzem Ihre Bestellung bei {sShop} nicht bis zum Ende durchgeführt - wir möchten Ihnen heute einen 5,00 € Gutschein zukommen lassen - und Ihnen hiermit die Bestell-Entscheidung bei {sShop} erleichtern.\r\n \r\nIhr Gutschein ist 2 Monate gültig und kann mit dem Code \"{$sVouchercode}\" eingelöst werden.\r\n\r\nWir würden uns freuen, Ihre Bestellung entgegen nehmen zu dürfen.'
        );

        $this->updateTemplate(
            'sORDERSTATEMAIL11',
            'Sehr geehrte{if $sUser.billing_salutation eq \"mr\"}r Herr{elseif $sUser.billing_salutation eq \"ms\"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n\nDer Status Ihrer Bestellung mit der Bestellnummer: {$sOrder.ordernumber} vom {$sOrder.ordertime|date_format:\" %d-%m-%Y\"} hat sich geändert.\n\nDer neue Status lautet nun {$sOrder.status_description}.'
        );

        $this->updateTemplate(
            'sORDERSTATEMAIL5',
            'Sehr geehrte{if $sUser.billing_salutation eq \"mr\"}r Herr{elseif $sUser.billing_salutation eq \"ms\"}Frau{/if}\n{$sUser.billing_firstname} {$sUser.billing_lastname},\n\nDer Status Ihrer Bestellung mit der Bestellnummer {$sOrder.ordernumber} vom {$sOrder.ordertime|date_format:\" %d.%m.%Y\"}\nhat sich geändert. Der neun Staus lautet nun {$sOrder.status_description}.'
        );

        $this->updateTemplate(
            'sORDERSTATEMAIL8',
            'Hallo {if $sUser.billing_salutation eq \"mr\"}Herr{elseif $sUser.billing_salutation eq \"ms\"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n\nder Bestellstatus für Ihre Bestellung {$sOrder.ordernumber} hat sich geändert!\nDie Bestellung hat jetzt den Status: {$sOrder.status_description}.\n\nDen aktuellen Status Ihrer Bestellung  können Sie auch jederzeit auf unserer Webseite im  Bereich \"Mein Konto\" - \"Meine Bestellungen\" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.'
        );

        $this->updateTemplate(
            'sORDERSTATEMAIL3',
            'Sehr geehrte{if $sUser.billing_salutation eq \"mr\"}r Herr{elseif $sUser.billing_salutation eq \"ms\"} Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n\nDer Status Ihrer Bestellung mit der Bestellnummer {$sOrder.ordernumber} vom {$sOrder.ordertime|date_format:\" %d.%m.%Y\"}\nhat sich geändert. Der neue Staus lautet nun \"{$sOrder.status_description}\".\n\n\nInformationen zu Ihrer Bestellung:\n==================================\n{foreach item=details key=position from=$sOrderDetails}\n{$position+1|fill:3} {$details.articleordernumber|fill:10:\" \":\"...\"} {$details.name|fill:30} {$details.quantity} x {$details.price|string_format:\"%.2f\"} {$sConfig.sCURRENCY}\n{/foreach}\n\nVersandkosten: {$sOrder.invoice_shipping} {$sConfig.sCURRENCY}\nNetto-Gesamt: {$sOrder.invoice_amount_net|string_format:\"%.2f\"} {$sConfig.sCURRENCY}\nGesamtbetrag inkl. MwSt.: {$sOrder.invoice_amount|string_format:\"%.2f\"} {$sConfig.sCURRENCY}'
        );

        $this->updateTemplate(
            'sORDERSTATEMAIL4',
            'Hallo {if $sUser.billing_salutation eq \"mr\"}Herr{elseif $sUser.billing_salutation eq \"ms\"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n\nder Bestellstatus für Ihre Bestellung {$sOrder.ordernumber} hat sich geändert!\nDie Bestellung hat jetzt den Status: {$sOrder.status_description}.\n\nDen aktuellen Status Ihrer Bestellung  können Sie  auch jederzeit auf unserer Webseite im  Bereich \"Mein Konto\" - \"Meine Bestellungen\" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.'
        );

        $this->updateTemplate(
            'sORDERSTATEMAIL6',
            'Hallo {if $sUser.billing_salutation eq \"mr\"}Herr{elseif $sUser.billing_salutation eq \"ms\"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n\nder Bestellstatus für Ihre Bestellung {$sOrder.ordernumber} hat sich geändert!\nDie Bestellung hat jetzt den Status: {$sOrder.status_description}.\n\nDen aktuellen Status Ihrer Bestellung  können Sie  auch jederzeit auf unserer Webseite im  Bereich \"Mein Konto\" - \"Meine Bestellungen\" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.'
        );

        $this->updateTemplate(
            'sBIRTHDAY',
            'Hallo {if $sUser.salutation eq \"mr\"}Herr{elseif $sUser.billing_salutation eq \"ms\"}Frau{/if} {$sUser.firstname} {$sUser.lastname},\n\nwir wünschen Ihnen alles Gute zum Geburtstag.'
        );
        $this->updateTemplate(
            'sARTICLESTOCK',
            'Hallo,\n\nfolgende Artikel haben den Mindestbestand unterschritten:\n\nBestellnummer Artikelname Bestand/Mindestbestand\n{foreach from=$sJob.articles item=sArticle key=key}\n{$sArticle.ordernumber} {$sArticle.name} {$sArticle.instock}/{$sArticle.stockmin}\n{/foreach}\n'
        );

        $this->updateTemplate(
            'sNEWSLETTERCONFIRMATION',
            'Hallo,\n\nvielen Dank für Ihre Newsletter-Anmeldung bei {config name=shopName}.'
        );

        $this->updateTemplate(
            'sOPTINNEWSLETTER',
            'Hallo,\n\nvielen Dank für Ihre Anmeldung zu unserem regelmäßig erscheinenden Newsletter.\n\nBitte bestätigen Sie die Anmeldung über den nachfolgenden Link: {$sConfirmLink}'
        );

        $this->updateTemplate(
            'sOPTINVOTE',
            'Hallo,\n\nvielen Dank für die Bewertung des Artikels {$sArticle.articleName}.\n\nBitte bestätigen Sie die Bewertung über nach den nachfolgenden Link: {$sConfirmLink}'
        );

        $this->updateTemplate(
            'sARTICLEAVAILABLE',
            'Hallo,\n\nIhr Artikel mit der Bestellnummer {$sOrdernumber} ist jetzt wieder verfügbar.\n\n{$sArticleLink}'
        );

        $this->updateTemplate(
            'sACCEPTNOTIFICATION',
            'Hallo,\n\nvielen Dank, dass Sie sich für die automatische E-Mail Benachrichtigung für den Artikel {$sArticleName} eingetragen haben.\n\nBitte bestätigen Sie die Benachrichtigung über den nachfolgenden Link:\n\n{$sConfirmLink}'
        );

        $this->updateTemplate(
            'sARTICLECOMMENT',
            'Hallo {if $sUser.salutation eq \"mr\"}Herr{elseif $sUser.billing_salutation eq \"ms\"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n\n\nSie haben bei uns vor einigen Tagen Artikel gekauft. Wir würden uns freuen, wenn Sie diese Artikel bewerten würden.<br/>\nSo helfen Sie uns, unseren Service weiter zu steigern und Sie können auf diesem Weg anderen Interessenten direkt Ihre Meinung mitteilen.\n\n\nHier finden Sie die Links zum Bewerten der von Ihnen gekauften Produkte.\n\n{foreach from=$sArticles item=sArticle key=key}\n{if !$sArticle.modus}\n{$sArticle.articleordernumber} {$sArticle.name} {$sArticle.link}\n{/if}\n{/foreach}',
            'Hallo {if $sUser.salutation eq \"mr\"}Herr{elseif $sUser.billing_salutation eq \"ms\"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n<br/>\nSie haben bei uns vor einigen Tagen Artikel gekauft. Wir würden uns freuen, wenn Sie diese Artikel bewerten würden.<br/>\nSo helfen Sie uns, unseren Service weiter zu steigern und Sie können auf diesem Weg anderen Interessenten direkt Ihre Meinung mitteilen.\n<br/><br/>\n\nHier finden Sie die Links zum Bewerten der von Ihnen gekauften Produkte.\n\n<table>\n {foreach from=$sArticles item=sArticle key=key}\n{if !$sArticle.modus}\n <tr>\n  <td>{$sArticle.articleordernumber}</td>\n  <td>{$sArticle.name}</td>\n  <td>\n  <a href=\"{$sArticle.link}\">link</a>\n  </td>\n </tr>\n{/if}\n {/foreach}\n</table>'
        );

        $this->updateTemplate(
            'sORDERSEPAAUTHORIZATION',
            'Hallo {$paymentInstance.firstName} {$paymentInstance.lastName}, im Anhang finden Sie ein Lastschriftmandat zu Ihrer Bestellung {$paymentInstance.orderNumber}. Bitte senden Sie uns das komplett ausgefüllte Dokument per Fax oder Email zurück.',
            'Hallo {$paymentInstance.firstName} {$paymentInstance.lastName}, im Anhang finden Sie ein Lastschriftmandat zu Ihrer Bestellung {$paymentInstance.orderNumber}. Bitte senden Sie uns das komplett ausgefüllte Dokument per Fax oder Email zurück.'
        );
    }

    /**
     * Update translations of mail templates
     */
    private function updateTranslations()
    {
        $this->updateTranslation(
            'sREGISTERCONFIRMATION',
            "Hello {salutation} {firstname} {lastname},\n\nthank you for your registration with our Shop.\n\nYou will gain access via the email address {sMAIL}\nand the password you have chosen.\n\nYou can have your password sent to you by email anytime.",
            "Hello {salutation} {firstname} {lastname},<br/><br/>\n\nThank you for your registration with our Shop.<br/><br/>\n\nYou will gain access via the email address {sMAIL} and the password you have chosen.<br/><br/>\n\nYou can have your password sent to you by email anytime."
        );

        $this->updateTranslation(
            'sORDER',
            "Hello {\$billingaddress.firstname} {\$billingaddress.lastname},\n\nThank you for your order at {config name=shopName} (Number: {\$sOrderNumber}) on {\$sOrderDay} at {\$sOrderTime}.\nInformation on your order:\n\nPos. Art.No.              Quantities         Price        Total\n{foreach item=details key=position from=\$sOrderDetails}\n{\$position+1|fill:4} {\$details.ordernumber|fill:20} {\$details.quantity|fill:6} {\$details.price|padding:8} EUR {\$details.amount|padding:8} EUR\n{\$details.articlename|wordwrap:49|indent:5}\n{/foreach}\n\nShipping costs: {\$sShippingCosts}\nTotal net: {\$sAmountNet}\n{if !\$sNet}\nTotal gross: {\$sAmount}\n{/if}\n\nSelected payment type: {\$additional.payment.description}\n{\$additional.payment.additionaldescription}\n{if \$additional.payment.name == \"debit\"}\nYour bank connection:\nAccount number: {\$sPaymentTable.account}\nBIN:{\$sPaymentTable.bankcode}\nWe will withdraw the money from your bank account within the next days.\n{/if}\n{if \$additional.payment.name == \"prepayment\"}\n\nOur bank connection:\nAccount: ###\nBIN: ###\n{/if}\n\n{if \$sComment}\nYour comment:\n{\$sComment}\n{/if}\n\nBilling address:\n{\$billingaddress.company}\n{\$billingaddress.firstname} {\$billingaddress.lastname}\n{\$billingaddress.street}\n{\$billingaddress.zipcode} {\$billingaddress.city}\n{\$billingaddress.phone}\n{\$additional.country.countryname}\n\nShipping address:\n{\$shippingaddress.company}\n{\$shippingaddress.firstname} {\$shippingaddress.lastname}\n{\$shippingaddress.street}\n{\$shippingaddress.zipcode} {\$shippingaddress.city}\n{\$additional.country.countryname}{if \$billingaddress.ustid}\n\n\nYour VAT-ID: {\$billingaddress.ustid}\nIn case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.{/if}",
            "<p>Hello {\$billingaddress.firstname} {\$billingaddress.lastname},<br/><br/>\n\nThank you for your order with {config name=shopName} (Nummer: {\$sOrderNumber}) on {\$sOrderDay} at {\$sOrderTime}.\n<br/>\n<br/>\n<strong>Information on your order:</strong></p>\n  <table width=\"80%\" border=\"0\" style=\"font-family:Arial, Helvetica, sans-serif; font-size:10px;\">\n    <tr>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Art.No.</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Pos.</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Art-Nr.</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Quantities</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Price</strong></td>\n      <td bgcolor=\"#F7F7F2\" style=\"border-bottom:1px solid #cccccc;\"><strong>Total</strong></td>\n    </tr>\n\n    {foreach item=details key=position from=\$sOrderDetails}\n    <tr>\n      <td rowspan=\"2\" style=\"border-bottom:1px solid #cccccc;\">{if \$details.image.src.1}<img src=\"{\$details.image.src.1}\" alt=\"{\$details.articlename}\" />{else} {/if}</td>\n      <td>{\$position+1|fill:4} </td>\n      <td>{\$details.ordernumber|fill:20}</td>\n      <td>{\$details.quantity|fill:6}</td>\n      <td>{\$details.price|padding:8}{\$sCurrency}</td>\n      <td>{\$details.amount|padding:8} {\$sCurrency}</td>\n    </tr>\n    <tr>\n      <td colspan=\"5\" style=\"border-bottom:1px solid #cccccc;\">{\$details.articlename|wordwrap:80|indent:4}</td>\n    </tr>\n    {/foreach}\n\n  </table>\n\n<p>\n  <br/>\n  <br/>\n    Shipping costs:: {\$sShippingCosts}<br/>\n    Total net: {\$sAmountNet}<br/>\n    {if !\$sNet}\n    Total gross: {\$sAmount}<br/>\n    {/if}\n  <br/>\n  <br/>\n    <strong>Selected payment type:</strong> {\$additional.payment.description}<br/>\n    {\$additional.payment.additionaldescription}\n    {if \$additional.payment.name == \"debit\"}\n    Your bank connection:<br/>\n    Account number: {\$sPaymentTable.account}<br/>\n    BIN:{\$sPaymentTable.bankcode}<br/>\n    We will withdraw the money from your bank account within the next days.<br/>\n    {/if}\n  <br/>\n  <br/>\n    {if \$additional.payment.name == \"prepayment\"}\n    Our bank connection:<br/>\n    {config name=bankAccount}\n    {/if}\n  <br/>\n  <br/>\n    <strong>Selected dispatch:</strong> {\$sDispatch.name}<br/>{\$sDispatch.description}\n</p>\n<p>\n  {if \$sComment}\n    <strong>Your comment:</strong><br/>\n    {\$sComment}<br/>\n  {/if}\n  <br/>\n  <br/>\n    <strong>Billing address:</strong><br/>\n    {\$billingaddress.company}<br/>\n    {\$billingaddress.firstname} {\$billingaddress.lastname}<br/>\n    {\$billingaddress.street}<br/>\n    {\$billingaddress.zipcode} {\$billingaddress.city}<br/>\n    {\$billingaddress.phone}<br/>\n    {\$additional.country.countryname}<br/>\n  <br/>\n  <br/>\n    <strong>Shipping address:</strong><br/>\n    {\$shippingaddress.company}<br/>\n    {\$shippingaddress.firstname} {\$shippingaddress.lastname}<br/>\n    {\$shippingaddress.street}<br/>\n    {\$shippingaddress.zipcode} {\$shippingaddress.city}<br/>\n    {\$additional.countryShipping.countryname}<br/>\n  <br/>\n    {if \$billingaddress.ustid}\n    Your VAT-ID: {\$billingaddress.ustid}<br/>\n    In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.\n    {/if}</p>"
        );

        $this->updateTranslation(
            'sTELLAFRIEND',
            "Hello,\n\n{sName} has found an interesting product for you on {sShop} that you should have a look at:\n\n{sArticle}\n{sLink}\n\n{sComment}"
        );

        $this->updateTranslation(
            'sPASSWORD',
            "Hello,\n\nYour access data for {sShopURL} is as follows:\nUser: {sMail}\nPassword: {sPassword}"
        );

        $this->updateTranslation(
            'sNOSERIALS',
            "Hello,\n\nThere is no additional free serial numbers available for the article {sArticleName}. Please provide new serial numbers immediately or deactivate the article. Please assign a serial number to the customer {sMail} manually."
        );

        $this->updateTranslation(
            'sVOUCHER',
            "Hello {customer},\n\n{user} has followed your recommendation and just ordered at {config name=shopName}.\nThis is why we give you a X € voucher, which you can redeem with your next order.\n\nYour voucher code is as follows: XXX"
        );

        $this->updateTranslation(
            'sCUSTOMERGROUPHACCEPTED',
            "Hello,\n\nyour merchant account {config name=shopName} has been unlocked.\n\nFrom now on, we will charge you the net purchase price."
        );

        $this->updateTranslation(
            'sCUSTOMERGROUPHREJECTED',
            "Dear customer,\n\nthank you for your interest in our trade prices. Unfortunately, we do not have a trading license yet so that we cannot accept you as a merchant.\n\nIn case of further questions please do not hesitate to contact us via telephone, fax or email."
        );

        $this->updateTranslation(
            'sORDERSTATEMAIL1',
            "Dear{if \$sUser.billing_salutation eq \"mr\"}Mr{elseif \$sUser.billing_salutation eq \"ms\"}Mrs{/if} {\$sUser.billing_firstname} {\$sUser.billing_lastname},\n\nThe status of your order with order number {\$sOrder.ordernumber} of {\$sOrder.ordertime|date_format:\" %d-%m-%Y\"} has changed. The new status is as follows: {\$sOrder.status_description}."
        );

        $this->updateTranslation(
            'sORDERSTATEMAIL2',
            "Dear{if \$sUser.billing_salutation eq \"mr\"}Mr{elseif \$sUser.billing_salutation eq \"ms\"}Mrs{/if} {\$sUser.billing_firstname} {\$sUser.billing_lastname},\n\nThe status of your order with order number {\$sOrder.ordernumber} of {\$sOrder.ordertime|date_format:\" %d-%m-%Y\"} has changed. The new status is as follows: {\$sOrder.status_description}."
        );

        $this->updateTranslation(
            'sCANCELEDQUESTION',
            "Dear customer,\n\nYou have recently aborted an order process on {sShop} - we are always working to make shopping with our shop as pleasant as possible. Therefore we would like to know why your order has failed.\n\nPlease tell us the reason why you have aborted your order. We will reward your additional effort by sending you a 5,00 €-voucher.\n\nThank you for your feedback."
        );

        $this->updateTranslation(
            'sCANCELEDVOUCHER',
            "Dear customer,\n\nYou have recently aborted an order process on {sShop} - today, we would like to give you a 5,00 Euro-voucher - and therefore make it easier for you to decide for an order with Demoshop.de.\n\nYour voucher is valid for two months and can be redeemed by entering the code \"{\$sVouchercode}\".\n\nWe would be pleased to accept your order!"
        );

        $this->updateTranslation(
            'sORDERSTATEMAIL11',
            "Dear {if \$sUser.billing_salutation eq \"mr\"}Mr{elseif \$sUser.billing_salutation eq \"ms\"}Mrs{/if} {\$sUser.billing_firstname} {\$sUser.billing_lastname},\n\nThe status of your order with order number {\$sOrder.ordernumber} of {\$sOrder.ordertime|date_format:\"%d-%m-%Y\"} has changed. The new status is as follows: {\$sOrder.status_description}."
        );

        $this->updateTranslation(
            'sORDERSTATEMAIL5',
            "Dear {if \$sUser.billing_salutation eq \"mr\"}Mr{elseif \$sUser.billing_salutation eq \"ms\"}Mrs{/if} {\$sUser.billing_firstname} {\$sUser.billing_lastname},\n\nThe status of your order with order number {\$sOrder.ordernumber} of {\$sOrder.ordertime|date_format:\"%d.%m.%Y\"} has changed. The new status is as follows: {\$sOrder.status_description}."
        );

        $this->updateTranslation(
            'sORDERSTATEMAIL8',
            "Dear {if \$sUser.billing_salutation eq \"mr\"}Mr{elseif \$sUser.billing_salutation eq \"ms\"}Mrs{/if} {\$sUser.billing_firstname} {\$sUser.billing_lastname},\n\nThe status of your order {\$sOrder.ordernumber} has changed!\nThe current status of your order is as follows: {\$sOrder.status_description}.\n\nYou can check the current status of your order on our website under \"My account\" - \"My orders\" anytime. But in case you have purchased without a registration or a customer account, you do not have this option."
        );

        $this->updateTranslation(
            'sORDERSTATEMAIL3',
            "Dear {if \$sUser.billing_salutation eq \"mr\"}Mr{elseif \$sUser.billing_salutation eq \"ms\"}Mrs{/if} {\$sUser.billing_firstname} {\$sUser.billing_lastname},\n\nThe status of your order {\$sOrder.ordernumber} of {\$sOrder.ordertime|date_format:\" %d.%m.%Y\"}\nhas changed. The new status is as follows: \"{\$sOrder.status_description}\".\n\n\nInformation on your order:\n==================================\n{foreach item=details key=position from=\$sOrderDetails}\n{\$position+1|fill:3} {\$details.articleordernumber|fill:10:\" \":\"...\"} {\$details.name|fill:30} {\$details.quantity} x {\$details.price|string_format:\"%.2f\"} {\$sConfig.sCURRENCY}\n{/foreach}\n\nShipping costs: {\$sOrder.invoice_shipping} {\$sConfig.sCURRENCY}\nNet total: {\$sOrder.invoice_amount_net|string_format:\"%.2f\"} {\$sConfig.sCURRENCY}\nTotal amount incl. VAT: {\$sOrder.invoice_amount|string_format:\"%.2f\"} {\$sConfig.sCURRENCY}"
        );

        $this->updateTranslation(
            'sORDERSTATEMAIL4',
            "Hello {if \$sUser.billing_salutation eq \"mr\"}Mr{elseif \$sUser.billing_salutation eq \"ms\"}Mrs{/if} {\$sUser.billing_firstname} {\$sUser.billing_lastname},\n\nThe order status of your order {\$sOrder.ordernumber} has changed!\nThe order now has the following status: {\$sOrder.status_description}.\n\nYou can check the current status of your order on our website under \"My account\" - \"My orders\" anytime. But in case you have purchased without a registration or a customer account, you do not have this option."
        );

        $this->updateTranslation(
            'sORDERSTATEMAIL6',
            "Hello {if \$sUser.billing_salutation eq \"mr\"}Mr{elseif \$sUser.billing_salutation eq \"ms\"}Mrs{/if} {\$sUser.billing_firstname} {\$sUser.billing_lastname},\n\nThe order status of your order {\$sOrder.ordernumber} has changed!\nYour order now has the following status: {\$sOrder.status_description}.\n\nYou can check the current status of your order on our website under \"My account\" - \"My orders\" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.\n\nBest regards,\nYour team of {config name=shopName}"
        );

        $this->updateTranslation(
            'sBIRTHDAY',
            "Hello {if \$sUser.salutation eq \"mr\"}Mr{elseif \$sUser.billing_salutation eq \"ms\"}Mrs{/if} {\$sUser.firstname} {\$sUser.lastname}, we wish you a happy birthday."
        );
        $this->updateTranslation(
            'sARTICLESTOCK',
            "Hello,\nthe following articles have undershot the minimum stock:\nOrder number Name of article Stock/Minimum stock\n{foreach from=\$sJob.articles item=sArticle key=key}\n{\$sArticle.ordernumber} {\$sArticle.name} {\$sArticle.instock}/{\$sArticle.stockmin}\n{/foreach}\n"
        );

        $this->updateTranslation(
            'sNEWSLETTERCONFIRMATION',
            "Hello,\n\nthank you for your newsletter subscription at {config name=shopName}"
        );

        $this->updateTranslation(
            'sOPTINNEWSLETTER',
            "Hello,\n\nthank you for signing up for our regularly published newsletter.\n\nPlease confirm your subscription by clicking the following link: {\$sConfirmLink}"
        );

        $this->updateTranslation(
            'sOPTINVOTE',
            "Hello,\n\nthank you for evaluating the article{\$sArticle.articleName}.\n\nPlease confirm the evaluation by clicking the following link: {\$sConfirmLink}"
        );

        $this->updateTranslation(
            'sARTICLEAVAILABLE',
            "Hello,\n\nyour article with the order number {\$sOrdernumber} is available again.\n\n{\$sArticleLink}"
        );

        $this->updateTranslation(
            'sACCEPTNOTIFICATION',
            "Hello,\n\nthank you for signing up for the automatic email notification for the article {\$sArticleName}.\nPlease confirm the notification by clicking the following link:\n\n{\$sConfirmLink}"
        );

        $this->updateTranslation(
            'sARTICLECOMMENT',
            "",
            "<p>Hello {if \$sUser.salutation eq \"mr\"}Mr{elseif \$sUser.billing_salutation eq \"ms\"}Mrs{/if} {\$sUser.billing_firstname} {\$sUser.billing_lastname},\n</p>\nYou have recently purchased articles from {config name=shopName}. We would be pleased if you could evaluate these items. Doing so, you can help us improve our services, and you have the opportunity to tell other customers your opinion.\nBy the way: You do not necessarily have to comment on the articles you have bought. You can select the ones you like best. We would welcome any feedback that you have.\nHere you can find the links to the evaluations of your purchased articles.\n<p>\n</p>\n<table>\n {foreach from=\$sArticles item=sArticle key=key}\n{if !\$sArticle.modus}\n <tr>\n  <td>{\$sArticle.articleordernumber}</td>\n  <td>{\$sArticle.name}</td>\n  <td>\n  <a href=\"{\$sArticle.link}\">link</a>\n  </td>\n </tr>\n{/if}\n {/foreach}\n</table>"
        );

        $this->updateTranslation(
            'sORDERSEPAAUTHORIZATION',
            "Hello {\$paymentInstance.firstName} {\$paymentInstance.lastName}, attached you will find the direct debit mandate form for your order {\$paymentInstance.orderNumber}. Please return the completely filled out document by fax or email.",
            "<div>Hello {\$paymentInstance.firstName} {\$paymentInstance.lastName},<br><br>attached you will find the direct debit mandate form for your order {\$paymentInstance.orderNumber}. Please return the completely filled out document by fax or email.</div>"
        );
    }
}
