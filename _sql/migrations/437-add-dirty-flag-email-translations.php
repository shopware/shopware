<?php

class Migrations_Migration437 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql('ALTER TABLE `s_core_config_mails` ADD `dirty` INT(1) NULL ;');
        $this->addSql('UPDATE `s_core_config_mails` SET `dirty` = 1;');
        $this->addSql('ALTER TABLE `s_core_translations` ADD `dirty` INT(1) NULL ;');
        $this->addSql("UPDATE `s_core_translations` SET `dirty` = 1 WHERE s_core_translations.objecttype = 'config_mails';");

        $this->setEmailsDirtyFlag();
        $this->setEmailTranslationsDirtyFlag();
    }

    private function setEmailsDirtyFlag()
    {
        $this->setEmailDirtyFlag(
            'sREGISTERCONFIRMATION',
            'Hallo {salutation} {firstname} {lastname},\n \nvielen Dank für Ihre Anmeldung in unserem Shop.\n \nSie erhalten Zugriff über Ihre eMail-Adresse {sMAIL}\nund dem von Ihnen gewählten Kennwort.\n \nSie können sich Ihr Kennwort jederzeit per eMail erneut zuschicken lassen.\n \nMit freundlichen Grüßen,\n \nIhr Team von {config name=shopName}',
            '<div style="font-family:arial; font-size:12px;">\n<img src="#" alt="Logo" />\n<p>\nHallo {salutation} {firstname} {lastname},<br/><br/>\n \nvielen Dank für Ihre Anmeldung in unserem Shop.<br/><br/>\n \nSie erhalten Zugriff über Ihre eMail-Adresse <strong>{sMAIL}</strong><br/>\nund dem von Ihnen gewählten Kennwort.<br/><br/>\n \nSie können sich Ihr Kennwort jederzeit per eMail erneut zuschicken lassen.<br/><br/>\n \nMit freundlichen Grüßen,<br/><br/>\n \nIhr Team von {config name=shopName}\n</p>\n</div>'
        );
        $this->setEmailDirtyFlag(
            'sORDER',
            'Hallo {$billingaddress.firstname} {$billingaddress.lastname},\n \nvielen Dank fuer Ihre Bestellung bei {config name=shopName} (Nummer: {$sOrderNumber}) am {$sOrderDay|date:"DATE_MEDIUM"} um {$sOrderTime|date:"TIME_SHORT"}.\nInformationen zu Ihrer Bestellung:\n \nPos. Art.Nr.              Menge         Preis        Summe\n{foreach item=details key=position from=$sOrderDetails}\n{$position+1|fill:4} {$details.ordernumber|fill:20} {$details.quantity|fill:6} {$details.price|padding:8} EUR {$details.amount|padding:8} EUR\n{$details.articlename|wordwrap:49|indent:5}\n{/foreach}\n \nVersandkosten: {$sShippingCosts}\nGesamtkosten Netto: {$sAmountNet}\n{if !$sNet}\nGesamtkosten Brutto: {$sAmount}\n{/if}\n \nGewählte Zahlungsart: {$additional.payment.description}\n{$additional.payment.additionaldescription}\n{if $additional.payment.name == "debit"}\nIhre Bankverbindung:\nKontonr: {$sPaymentTable.account}\nBLZ:{$sPaymentTable.bankcode}\nWir ziehen den Betrag in den nächsten Tagen von Ihrem Konto ein.\n{/if}\n{if $additional.payment.name == "prepayment"}\n \nUnsere Bankverbindung:\n{config name=bankAccount}\n{/if}\n \n{if $sComment}\nIhr Kommentar:\n{$sComment}\n{/if}\n \nRechnungsadresse:\n{$billingaddress.company}\n{$billingaddress.firstname} {$billingaddress.lastname}\n{$billingaddress.street}\n{$billingaddress.zipcode} {$billingaddress.city}\n{$billingaddress.phone}\n{$additional.country.countryname}\n \nLieferadresse:\n{$shippingaddress.company}\n{$shippingaddress.firstname} {$shippingaddress.lastname}\n{$shippingaddress.street}\n{$shippingaddress.zipcode} {$shippingaddress.city}\n{$additional.country.countryname}\n \n{if $billingaddress.ustid}\nIhre Umsatzsteuer-ID: {$billingaddress.ustid}\nBei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland\nbestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit.\n{/if}\n \n \nFür Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung. \n\nWir wünschen Ihnen noch einen schönen Tag.\n \n{config name=address}\n\n ',
            '<div style="font-family:arial; font-size:12px;">\n \n<p>Hallo {$billingaddress.firstname} {$billingaddress.lastname},<br/><br/>\n \nvielen Dank fuer Ihre Bestellung bei {config name=shopName} (Nummer: {$sOrderNumber}) am {$sOrderDay|date:"DATE_MEDIUM"} um {$sOrderTime|date:"TIME_SHORT"}.\n<br/>\n<br/>\n<strong>Informationen zu Ihrer Bestellung:</strong></p>\n  <table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:10px;">\n    <tr>\n      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Artikel</strong></td>\n      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>\n      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Art-Nr.</strong></td>\n      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Menge</strong></td>\n      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Preis</strong></td>\n      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Summe</strong></td>\n    </tr>\n \n    {foreach item=details key=position from=$sOrderDetails}\n    <tr>\n      <td rowspan="2" style="border-bottom:1px solid #cccccc;">{if $details.image.src.1}<img src="{$details.image.src.1}" alt="{$details.articlename}" />{else} {/if}</td>\n      <td>{$position+1|fill:4} </td>\n      <td>{$details.ordernumber|fill:20}</td>\n      <td>{$details.quantity|fill:6}</td>\n      <td>{$details.price|padding:8}{$sCurrency}</td>\n      <td>{$details.amount|padding:8} {$sCurrency}</td>\n    </tr>\n    <tr>\n      <td colspan="5" style="border-bottom:1px solid #cccccc;">{$details.articlename|wordwrap:80|indent:4}</td>\n    </tr>\n    {/foreach}\n \n  </table>\n \n<p>\n  <br/>\n  <br/>\n    Versandkosten: {$sShippingCosts}<br/>\n    Gesamtkosten Netto: {$sAmountNet}<br/>\n    {if !$sNet}\n    Gesamtkosten Brutto: {$sAmount}<br/>\n    {/if}\n  <br/>\n  <br/>\n    <strong>Gewählte Zahlungsart:</strong> {$additional.payment.description}<br/>\n    {$additional.payment.additionaldescription}\n    {if $additional.payment.name == "debit"}\n    Ihre Bankverbindung:<br/>\n    Kontonr: {$sPaymentTable.account}<br/>\n    BLZ:{$sPaymentTable.bankcode}<br/>\n    Wir ziehen den Betrag in den nächsten Tagen von Ihrem Konto ein.<br/>\n    {/if}\n  <br/>\n  <br/>\n    {if $additional.payment.name == "prepayment"}\n    Unsere Bankverbindung:<br/>\n    {config name=bankAccount}\n    {/if} \n  <br/>\n  <br/>\n    <strong>Gewählte Versandart:</strong> {$sDispatch.name}<br/>{$sDispatch.description}\n</p>\n<p>\n  {if $sComment}\n    <strong>Ihr Kommentar:</strong><br/>\n    {$sComment}<br/>\n  {/if} \n  <br/>\n  <br/>\n    <strong>Rechnungsadresse:</strong><br/>\n    {$billingaddress.company}<br/>\n    {$billingaddress.firstname} {$billingaddress.lastname}<br/>\n    {$billingaddress.street}<br/>\n    {$billingaddress.zipcode} {$billingaddress.city}<br/>\n    {$billingaddress.phone}<br/>\n    {$additional.country.countryname}<br/>\n  <br/>\n  <br/>\n    <strong>Lieferadresse:</strong><br/>\n    {$shippingaddress.company}<br/>\n    {$shippingaddress.firstname} {$shippingaddress.lastname}<br/>\n    {$shippingaddress.street}<br/>\n    {$shippingaddress.zipcode} {$shippingaddress.city}<br/>\n    {$additional.countryShipping.countryname}<br/>\n  <br/>\n    {if $billingaddress.ustid}\n    Ihre Umsatzsteuer-ID: {$billingaddress.ustid}<br/>\n    Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland<br/>\n    bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit.<br/>\n    {/if}\n  <br/>\n  <br/>\n    Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung. Sie erreichen uns wie folgt: <br/>{config name=address}\n    <br/>\n    Mit freundlichen Grüßen,<br/>\n    Ihr Team von {config name=shopName}<br/>\n</p>\n</div>'
        );
        $this->setEmailDirtyFlag(
            'sTELLAFRIEND',
            'Hallo,\r\n\r\n{sName} hat für Sie bei {sShop} ein interessantes Produkt gefunden, dass Sie sich anschauen sollten:\r\n\r\n{sArticle}\r\n{sLink}\r\n\r\n{sComment}\r\n\r\nBis zum naechsten Mal und mit freundlichen Gruessen,\r\n\r\nIhre Kontaktdaten',
            ''
        );
        $this->setEmailDirtyFlag(
            'sPASSWORD',
            'Hallo,\n\nIhre Zugangsdaten zu {sShopURL} lauten wie folgt:\nBenutzer: {sMail}\nPasswort: {sPassword}\n\nBis zum naechsten Mal und mit freundlichen Gruessen,\n\n{config name=address}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sNOSERIALS',
            'Hallo,\r\n\r\nes sind keine weiteren freien Seriennummern für den Artikel {sArticleName} verfügbar. Bitte stellen Sie umgehend neue Seriennummern ein oder deaktivieren Sie den Artikel. \r\n\r\n{config name=shopName}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sVOUCHER',
            'Hallo {customer},\n\n{user} ist Ihrer Empfehlung gefolgt und hat so eben im Demoshop bestellt.\nWir schenken Ihnen deshalb einen X € Gutschein, den Sie bei Ihrer nächsten Bestellung einlösen können.\n			\nIhr Gutschein-Code lautet: XXX\n			\nViele Grüße,\n\nIhr Team von {config name=shopName}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sCUSTOMERGROUPHACCEPTED',
            'Hallo,\n\nIhr Händleraccount auf {config name=shopName} wurde freigeschaltet\n\nAb sofort kaufen Sie zum Netto-EK bei uns ein.\n\nMit freundlichen Grüßen,\n\nIhr Team von {config name=shopName}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sCUSTOMERGROUPHREJECTED',
            'Sehr geehrter Kunde,\n\nvielen Dank für Ihr Interesse an unseren Fachhandelspreisen. Leider liegt uns aber noch kein Gewerbenachweis vor bzw. leider können wir Sie nicht als Fachhändler anerkennen.\n\nBei Rückfragen aller Art können Sie uns gerne telefonisch, per Fax oder per Mail diesbezüglich erreichen.\n\nMit freundlichen Grüßen\n\nIhr Team von {config name=shopName}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL1',
            'Sehr geehrte{if $sUser.billing_salutation eq "mr"}r Herr{elseif $sUser.billing_salutation eq "ms"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\r\n\r\nDer Status Ihrer Bestellung mit der Bestellnummer: {$sOrder.ordernumber} vom {$sOrder.ordertime|date_format:" %d-%m-%Y"} hat sich geändert. Der neue Status lautet nun {$sOrder.status_description}.',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL2',
            'Sehr geehrte{if $sUser.billing_salutation eq "mr"}r Herr{elseif $sUser.billing_salutation eq "ms"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\r\n\r\nDer Status Ihrer Bestellung mit der Bestellnummer: {$sOrder.ordernumber} vom {$sOrder.ordertime|date_format:" %d-%m-%Y"} hat sich geändert. Der neue Status lautet nun {$sOrder.status_description}.',
            ''
        );
        $this->setEmailDirtyFlag(
            'sCANCELEDQUESTION',
            'Lieber Kunde,\r\n \r\nsie haben vor kurzem Ihre Bestellung auf Demoshop.de nicht bis zum Ende durchgeführt - wir sind stets bemüht unseren Kunden das Einkaufen in unserem Shop so angenehm wie möglich zu machen und würden deshalb gerne wissen, woran Ihr Einkauf bei uns gescheitert ist.\r\n \r\nBitte lassen Sie uns doch den Grund für Ihren Bestellabbruch zukommen, Ihren Aufwand entschädigen wir Ihnen in jedem Fall mit einem 5,00 € Gutschein.\r\n \r\nVielen Dank für Ihre Unterstützung.',
            ''
        );
        $this->setEmailDirtyFlag(
            'sCANCELEDVOUCHER',
            'Lieber Kunde,\r\n \r\nsie haben vor kurzem Ihre Bestellung auf Demoshop.de nicht bis zum Ende durchgeführt - wir möchten Ihnen heute einen 5,00 € Gutschein zukommen lassen - und Ihnen hiermit die Bestell-Entscheidung auf demoshop.de erleichtern.\r\n \r\nIhr Gutschein ist 2 Monate gültig und kann mit dem Code "{$sVouchercode}" eingelöst werden.\r\n\r\nWir würden uns freuen, Ihre Bestellung entgegen nehmen zu dürfen.\r\n',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL9',
            '',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL10',
            '',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL11',
            'Sehr geehrte{if $sUser.billing_salutation eq "mr"}r Herr{elseif $sUser.billing_salutation eq "ms"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n\nDer Status Ihrer Bestellung mit der Bestellnummer: {$sOrder.ordernumber} vom {$sOrder.ordertime|date_format:" %d-%m-%Y"} hat sich geändert. \n\nDer neue Status lautet nun {$sOrder.status_description}.',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL13',
            '',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL16',
            '',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL15',
            '',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL14',
            '',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL12',
            '',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL5',
            'Sehr geehrte{if $sUser.billing_salutation eq "mr"}r Herr{elseif $sUser.billing_salutation eq "ms"}Frau{/if} \n{$sUser.billing_firstname} {$sUser.billing_lastname},\n \nDer Status Ihrer Bestellung mit der Bestellnummer {$sOrder.ordernumber} vom {$sOrder.ordertime|date_format:" %d.%m.%Y"} \nhat sich geändert. Der neun Staus lautet nun {$sOrder.status_description}.\n \nMit freundlichen Grüßen,\nIhr Team von {config name=shopName}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL8',
            'Hallo {if $sUser.billing_salutation eq "mr"}Herr{elseif $sUser.billing_salutation eq "ms"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n \nder Bestellstatus für Ihre Bestellung {$sOrder.ordernumber} hat sich geändert!\nDie Bestellung hat jetzt den Status: {$sOrder.status_description}.\n\nDen aktuellen Status Ihrer Bestellung  können Sie  auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.\n \nMit freundlichen Grüßen,\nIhr Team von {config name=shopName}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL3',
            'Sehr geehrte{if $sUser.billing_salutation eq "mr"}r Herr{elseif $sUser.billing_salutation eq "ms"} Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n \nDer Status Ihrer Bestellung mit der Bestellnummer {$sOrder.ordernumber} vom {$sOrder.ordertime|date_format:" %d.%m.%Y"} \nhat sich geändert. Der neue Staus lautet nun "{$sOrder.status_description}".\n \n \nInformationen zu Ihrer Bestellung:\n================================== \n{foreach item=details key=position from=$sOrderDetails}\n{$position+1|fill:3} {$details.articleordernumber|fill:10:" ":"..."} {$details.name|fill:30} {$details.quantity} x {$details.price|string_format:"%.2f"} {$sConfig.sCURRENCY}\n{/foreach}\n \nVersandkosten: {$sOrder.invoice_shipping} {$sConfig.sCURRENCY}\nNetto-Gesamt: {$sOrder.invoice_amount_net|string_format:"%.2f"} {$sConfig.sCURRENCY}\nGesamtbetrag inkl. MwSt.: {$sOrder.invoice_amount|string_format:"%.2f"} {$sConfig.sCURRENCY}\n \nMit freundlichen Grüßen,\nIhr Team von {config name=shopName}\n\n',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL17',
            '',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL4',
            'Hallo {if $sUser.billing_salutation eq "mr"}Herr{elseif $sUser.billing_salutation eq "ms"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n \nder Bestellstatus für Ihre Bestellung {$sOrder.ordernumber} hat sich geändert!\nDie Bestellung hat jetzt den Status: {$sOrder.status_description}.\n\nDen aktuellen Status Ihrer Bestellung  können Sie  auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.\n \nMit freundlichen Grüßen,\nIhr Team von {config name=shopName}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL6',
            'Hallo {if $sUser.billing_salutation eq "mr"}Herr{elseif $sUser.billing_salutation eq "ms"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n \nder Bestellstatus für Ihre Bestellung {$sOrder.ordernumber} hat sich geändert!\nDie Bestellung hat jetzt den Status: {$sOrder.status_description}.\n\nDen aktuellen Status Ihrer Bestellung  können Sie  auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen. Sollten Sie allerdings den Kauf ohne Registrierung, also ohne Anlage eines Kundenkontos, gewählt haben, steht Ihnen diese Möglichkeit nicht zur Verfügung.\n \nMit freundlichen Grüßen,\nIhr Team von {config name=shopName}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL18',
            '',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL19',
            '',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL20',
            '',
            ''
        );
        $this->setEmailDirtyFlag(
            'sORDERSTATEMAIL7',
            '',
            ''
        );
        $this->setEmailDirtyFlag(
            'sBIRTHDAY',
            'Hallo {if $sUser.salutation eq "mr"}Herr{elseif $sUser.billing_salutation eq "ms"}Frau{/if} {$sUser.firstname} {$sUser.lastname},\n\nMit freundlichen Grüßen,\n\nIhr Team von {config name=shopName}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sARTICLESTOCK',
            'Hallo,\n\nfolgende Artikel haben den Mindestbestand unterschritten:\n\nBestellnummer Artikelname Bestand/Mindestbestand \n{foreach from=$sJob.articles item=sArticle key=key}\n{$sArticle.ordernumber} {$sArticle.name} {$sArticle.instock}/{$sArticle.stockmin} \n{/foreach}\n',
            ''
        );
        $this->setEmailDirtyFlag(
            'sNEWSLETTERCONFIRMATION',
            'Hallo,\n\nvielen Dank für Ihre Newsletter-Anmeldung bei {config name=shopName}.\n\nViele Grüße,\n\nIhr Team von {config name=shopName}\n\n\nKontaktdaten:\n{config name=address}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sOPTINNEWSLETTER',
            'Hallo, \n\nvielen Dank für Ihre Anmeldung zu unserem regelmäßig erscheinenden Newsletter. \n\nBitte bestätigen Sie die Anmeldung über den nachfolgenden Link: {$sConfirmLink} \n\n\nViele Grüße\n\nIhr Team von {config name=shopName}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sOPTINVOTE',
            'Hallo, \n\nvielen Dank für die Bewertung des Artikels {$sArticle.articleName}. \n\nBitte bestätigen Sie die Bewertung über nach den nachfolgenden Link: {$sConfirmLink} \n\nViele Grüße',
            ''
        );
        $this->setEmailDirtyFlag(
            'sARTICLEAVAILABLE',
            'Hallo,\n\nIhr Artikel mit der Bestellnummer {$sOrdernumber} ist jetzt wieder verfügbar.\n\n{$sArticleLink}\n\nViele Grüße\n\nIhr Team von {config name=shopName}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sACCEPTNOTIFICATION',
            'Hallo,\n\nvielen Dank, dass Sie sich für die automatische e-Mail Benachrichtigung für den Artikel {$sArticleName} eingetragen haben.\n\nBitte bestätigen Sie die Benachrichtigung über den nachfolgenden Link: \n\n{$sConfirmLink}\n\nViele Grüße \n\nIhr Team von {config name=shopName}',
            ''
        );
        $this->setEmailDirtyFlag(
            'sARTICLECOMMENT',
            'Hallo {if $sUser.salutation eq "mr"}Herr{elseif $sUser.billing_salutation eq "ms"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n\n\nSie haben bei uns vor einigen Tagen Artikel gekauft. Wir würden uns freuen, wenn Sie diese Artikel bewerten würden.<br/>\nSo helfen Sie uns, unseren Service weiter zu steigern und Sie können auf diesem Weg anderen Interessenten direkt Ihre Meinung mitteilen.\n\n\nHier finden Sie die Links zum Bewerten der von Ihnen gekauften Produkte.\n\n{foreach from=$sArticles item=sArticle key=key}\n{if !$sArticle.modus}\n{$sArticle.articleordernumber} {$sArticle.name} {$sArticle.link}\n{/if}\n{/foreach}\n\nViele Grüße,\n\nIhr Team von {config name=shopName}',
            '<div>\nHallo {if $sUser.salutation eq "mr"}Herr{elseif $sUser.billing_salutation eq "ms"}Frau{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},\n<br/>\nSie haben bei uns vor einigen Tagen Artikel gekauft. Wir würden uns freuen, wenn Sie diese Artikel bewerten würden.<br/>\nSo helfen Sie uns, unseren Service weiter zu steigern und Sie können auf diesem Weg anderen Interessenten direkt Ihre Meinung mitteilen.\n<br/><br/>\n\nHier finden Sie die Links zum Bewerten der von Ihnen gekauften Produkte.\n\n<table>\n {foreach from=$sArticles item=sArticle key=key}\n{if !$sArticle.modus}\n <tr>\n  <td>{$sArticle.articleordernumber}</td>\n  <td>{$sArticle.name}</td>\n  <td>\n  <a href="{$sArticle.link}">link</a>\n  </td>\n </tr>\n{/if}\n {/foreach}\n</table>\n\n\nViele Grüße,<br />\nIhr Team von {config name=shopName}\n</div>'
        );
        $this->setEmailDirtyFlag(
            'sORDERSEPAAUTHORIZATION',
            'Hallo {$paymentInstance.firstName} {$paymentInstance.lastName}, im Anhang finden Sie ein Lastschriftmandat zu Ihrer Bestellung {$paymentInstance.orderNumber}. Bitte senden Sie uns das komplett ausgefüllte Dokument per Fax oder Email zurück.',
            'Hallo {$paymentInstance.firstName} {$paymentInstance.lastName}, im Anhang finden Sie ein Lastschriftmandat zu Ihrer Bestellung {$paymentInstance.orderNumber}. Bitte senden Sie uns das komplett ausgefüllte Dokument per Fax oder Email zurück.'
        );
    }

    private function setEmailTranslationsDirtyFlag()
    {
        $defaultData = $this->getDefaultTranslationValues();

        foreach($defaultData as $translationDefault) {
            $this->setEmailTranslationDirtyFlag($translationDefault['name'], $translationDefault['objectdata']);
        }
    }

    /**
     * Helper method to set the dirty flag of email templates
     * @param string $name
     * @param string $content
     * @param string $contentHtml
     */
    private function setEmailDirtyFlag($name, $content, $contentHtml)
    {
        $sql = <<<SQL
UPDATE `s_core_config_mails` SET `dirty` = IF (`content` = '$content' AND `contentHTML` = '$contentHtml', 0, 1) WHERE `name` = "$name";
SQL;
        $this->addSql($sql);
    }

    /**
     * Helper method to set the dirty flag of email template translations
     * @param string $name
     * @param string $objectData
     */
    private function setEmailTranslationDirtyFlag($name, $objectData)
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
        $dirty = (int) ($objectData != $translation['objectdata']);

        $sql = <<<SQL
UPDATE `s_core_translations` SET `dirty`= $dirty WHERE `id` = $id
SQL;

        $this->addSql($sql);
    }

    /**
     * Helper method that returns the default translation data
     * for comparison with existing data in the DB
     *
     * @return array
     */
    private function getDefaultTranslationValues()
    {
        return array(
            array('name' => 'sACCEPTNOTIFICATION','objectdata' => 'a:2:{s:7:"subject";s:39:"Please confirm your e-mail notification";s:7:"content";s:240:"Hello, 

Thank you for signing up for the automatical e-Mail notification for the article {$sArticleName}. 
Please confirm the notification by clicking the following link:

{$sConfirmLink} 

Best regards

Your Team of {config name=shopName}";}'),
            array('name' => 'sARTICLEAVAILABLE','objectdata' => 'a:2:{s:7:"subject";s:31:"Your article is available again";s:7:"content";s:148:"Hello, 

Your article with the order number {$sOrdernumber} is available again. 

{$sArticleLink} 

Best regards
Your Team of {config name=shopName}";}'),
            array('name' => 'sARTICLECOMMENT','objectdata' => 'a:2:{s:7:"subject";s:16:"Evaluate article";s:7:"content";s:948:"<p>Hello {if $sUser.salutation eq "mr"}Mr{elseif $sUser.billing_salutation eq "ms"}Mrs{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},
</p>
You have recently purchased articles from {config name=shopName}. We would be pleased if you could evaluate these items. Doing so, you can help us improve our services, and you have the opportunity to tell other customers your opinion. 
By the way: You do not necessarily have to comment on the articles you have bought. You can select the ones you like best. We would welcome any feedback that you have. 
Here you can find the links to the evaluations of your purchased articles.
<p>
</p>
<table>
 {foreach from=$sArticles item=sArticle key=key}
{if !$sArticle.modus}
 <tr>
  <td>{$sArticle.articleordernumber}</td>
  <td>{$sArticle.name}</td>
  <td>
  <a href="{$sArticle.link}">link</a>
  </td>
 </tr>
{/if}
 {/foreach}
</table>

<p>
Best regards,<br />
Your team of {config name=shopName}
</p>";}'),
            array('name' => 'sARTICLESTOCK','objectdata' => 'a:2:{s:7:"subject";s:83:"Stock level of {$sData.count} article{if $sData.count>1}s{/if} under minimum stock ";s:7:"content";s:260:"Hello,
The following articles have undershot the minimum stock:
Order number Name of article Stock/Minimum stock 
{foreach from=$sJob.articles item=sArticle key=key}
{$sArticle.ordernumber} {$sArticle.name} {$sArticle.instock}/{$sArticle.stockmin} 
{/foreach}
";}'),
            array('name' => 'sBIRTHDAY','objectdata' => 'a:2:{s:7:"subject";s:40:"Happy Birthday from {$sConfig.sSHOPNAME}";s:7:"content";s:174:"Hello {if $sUser.salutation eq "mr"}Mr{elseif $sUser.billing_salutation eq "ms"}Mrs{/if} {$sUser.firstname} {$sUser.lastname},

Best regards
Your team of {$sConfig.sSHOPNAME}";}'),
            array('name' => 'sCANCELEDQUESTION','objectdata' => 'a:2:{s:7:"subject";s:69:"Your aborted order process - Send us your feedback and get a voucher!";s:7:"content";s:378:"Dear customer,
 
You have recently aborted an order process on Demoshop.de - we are always working to make shopping with our shop as pleasant as possible. Therefore we would like to know why your order has failed.
 
Please tell us the reason why you have aborted your order. We will reward your additional effort by sending you a 5,00 €-voucher. 
 
Thank you for your feedback";}'),
            array('name' => 'sCANCELEDVOUCHER','objectdata' => 'a:2:{s:7:"subject";s:50:"Your aborted order process - Voucher code enclosed";s:7:"content";s:351:"Dear customer,
 
You have recently aborted an order process on Demoshop.de - today, we would like to give you a 5,00 Euro-voucher - and therefore make it easier for you to decide for an order with Demoshop.de.
 
Your voucher is valid for two months and can be redeemed by entering the code "{$sVouchercode}".

We would be pleased to accept your order!";}'),
            array('name' => 'sCUSTOMERGROUPHACCEPTED','objectdata' => 'a:2:{s:7:"subject";s:39:"Your merchant account has been unlocked";s:7:"content";s:186:"Hello,

Your merchant account {config name=shopName} has been unlocked.
  
From now on, we will charge you the net purchase price. 
  
Best regards
  
Your team of {config name=shopName}";}'),
            array('name' => 'sCUSTOMERGROUPHREJECTED','objectdata' => 'a:2:{s:7:"subject";s:41:"Your trader account has not been accepted";s:7:"content";s:307:"Dear customer,

Thank you for your interest in our trade prices. Unfortunately, we do not have a trading license yet so that we cannot accept you as a trader. 

In case of further questions please do not hesitate to contact us via telephone, fax or email. 

Best regards

Your Team of {config name=shopName}";}'),
            array('name' => 'sNEWSLETTERCONFIRMATION','objectdata' => 'a:2:{s:7:"subject";s:42:"Thank you for your newsletter subscription";s:7:"content";s:78:"Hello,

Thank you for your newsletter subscription at {config name=shopName}

";}'),
            array('name' => 'sNOSERIALS','objectdata' => 'a:2:{s:7:"subject";s:53:"Attention - no free serial numbers for {sArticleName}";s:7:"content";s:269:"Hello,

There is no additional free serial numbers available for the article {sArticleName}. Please provide new serial numbers immediately or deactivate the article. Please assign a serial number to the customer {sMail} manually.

Best regards,

{config name=shopName}
";}'),
            array('name' => 'sOPTINNEWSLETTER','objectdata' => 'a:2:{s:7:"subject";s:43:"Please confirm your newsletter subscription";s:7:"content";s:208:"Hello, 

Thank you for signing up for our regularly published newsletter. 

Please confirm your subscription by clicking the following link: {$sConfirmLink} 

Best regards

Your Team of {config name=shopName}";}'),
            array('name' => 'sOPTINVOTE','objectdata' => 'a:2:{s:7:"subject";s:38:"Please confirm your article evaluation";s:7:"content";s:164:"Hello, 

Thank you for evaluating the article{$sArticle.articleName}. 

Please confirm the evaluation by clicking the following link: {$sConfirmLink} 

Best regards";}'),
            array('name' => 'sORDER','objectdata' => 'a:3:{s:7:"subject";s:28:"Your order with the demoshop";s:7:"content";s:1739:"Hello {$billingaddress.firstname} {$billingaddress.lastname},
 
Thank you for your order at {config name=shopName} (Number: {$sOrderNumber}) on {$sOrderDay} at {$sOrderTime}.
Information on your order:
 
Pos. Art.No.              Quantities         Price        Total
{foreach item=details key=position from=$sOrderDetails}
{$position+1|fill:4} {$details.ordernumber|fill:20} {$details.quantity|fill:6} {$details.price|padding:8} EUR {$details.amount|padding:8} EUR
{$details.articlename|wordwrap:49|indent:5}
{/foreach}
 
Shipping costs: {$sShippingCosts}
Total net: {$sAmountNet}
{if !$sNet}
Total gross: {$sAmount}
{/if}
 
Selected payment type: {$additional.payment.description}
{$additional.payment.additionaldescription}
{if $additional.payment.name == "debit"}
Your bank connection:
Account number: {$sPaymentTable.account}
BIN:{$sPaymentTable.bankcode}
We will withdraw the money from your bank account within the next days.
{/if}
{if $additional.payment.name == "prepayment"}
 
Our bank connection:
Account: ###
BIN: ###
{/if}
 
{if $sComment}
Your comment:
{$sComment}
{/if}
 
Billing address:
{$billingaddress.company}
{$billingaddress.firstname} {$billingaddress.lastname}
{$billingaddress.street}
{$billingaddress.zipcode} {$billingaddress.city}
{$billingaddress.phone}
{$additional.country.countryname}
 
Shipping address:
{$shippingaddress.company}
{$shippingaddress.firstname} {$shippingaddress.lastname}
{$shippingaddress.street}
{$shippingaddress.zipcode} {$shippingaddress.city}
{$additional.country.countryname}
 
{if $billingaddress.ustid}
Your VAT-ID: {$billingaddress.ustid}
In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax. 
{/if}
 ";s:11:"contentHtml";s:3493:"<div style="font-family:arial; font-size:12px;">
 
<p>Hello {$billingaddress.firstname} {$billingaddress.lastname},<br/><br/>
 
Thank you for your order with {config name=shopName} (Nummer: {$sOrderNumber}) on {$sOrderDay} at {$sOrderTime}.
<br/>
<br/>
<strong>Information on your order:</strong></p>
  <table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:10px;">
    <tr>
      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Art.No.</strong></td>
      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>
      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Art-Nr.</strong></td>
      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Quantities</strong></td>
      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Price</strong></td>
      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Total</strong></td>
    </tr>
 
    {foreach item=details key=position from=$sOrderDetails}
    <tr>
      <td rowspan="2" style="border-bottom:1px solid #cccccc;">{if $details.image.src.1}<img src="{$details.image.src.1}" alt="{$details.articlename}" />{else} {/if}</td>
      <td>{$position+1|fill:4} </td>
      <td>{$details.ordernumber|fill:20}</td>
      <td>{$details.quantity|fill:6}</td>
      <td>{$details.price|padding:8}{$sCurrency}</td>
      <td>{$details.amount|padding:8} {$sCurrency}</td>
    </tr>
    <tr>
      <td colspan="5" style="border-bottom:1px solid #cccccc;">{$details.articlename|wordwrap:80|indent:4}</td>
    </tr>
    {/foreach}
 
  </table>
 
<p>
  <br/>
  <br/>
    Shipping costs:: {$sShippingCosts}<br/>
    Total net: {$sAmountNet}<br/>
    {if !$sNet}
    Total gross: {$sAmount}<br/>
    {/if}
  <br/>
  <br/>
    <strong>Selected payment type:</strong> {$additional.payment.description}<br/>
    {$additional.payment.additionaldescription}
    {if $additional.payment.name == "debit"}
    Your bank connection:<br/>
    Account number: {$sPaymentTable.account}<br/>
    BIN:{$sPaymentTable.bankcode}<br/>
    We will withdraw the money from your bank account within the next days.<br/>
    {/if}
  <br/>
  <br/>
    {if $additional.payment.name == "prepayment"}
    Our bank connection:<br/>
    {config name=bankAccount}
    {/if} 
  <br/>
  <br/>
    <strong>Selected dispatch:</strong> {$sDispatch.name}<br/>{$sDispatch.description}
</p>
<p>
  {if $sComment}
    <strong>Your comment:</strong><br/>
    {$sComment}<br/>
  {/if} 
  <br/>
  <br/>
    <strong>Billing address:</strong><br/>
    {$billingaddress.company}<br/>
    {$billingaddress.firstname} {$billingaddress.lastname}<br/>
    {$billingaddress.street}<br/>
    {$billingaddress.zipcode} {$billingaddress.city}<br/>
    {$billingaddress.phone}<br/>
    {$additional.country.countryname}<br/>
  <br/>
  <br/>
    <strong>Shipping address:</strong><br/>
    {$shippingaddress.company}<br/>
    {$shippingaddress.firstname} {$shippingaddress.lastname}<br/>
    {$shippingaddress.street}<br/>
    {$shippingaddress.zipcode} {$shippingaddress.city}<br/>
    {$additional.countryShipping.countryname}<br/>
  <br/>
    {if $billingaddress.ustid}
    Your VAT-ID: {$billingaddress.ustid}<br/>
    In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax. 
    {/if}
  <br/>
  <br/>

    Your Team of {config name=shopName}<br/>
</p>
</div>";}'),
            array('name' => 'sORDERSEPAAUTHORIZATION','objectdata' => 'a:3:{s:7:"subject";s:25:"SEPA direct debit mandate";s:7:"content";s:275:"Hello {$paymentInstance.firstName} {$paymentInstance.lastName},Attached you will find the direct debit mandate form for your order {$paymentInstance.orderNumber}. Please return the completely filled out document by fax or email. Best regards. The {config name=shopName} team.";s:11:"contentHtml";s:311:"<div>Hello {$paymentInstance.firstName} {$paymentInstance.lastName},<br><br>Attached you will find the direct debit mandate form for your order {$paymentInstance.orderNumber}. Please return the completely filled out document by fax or email.<br/><br/>Best regards,<br/><br/>The {config name=shopName} team</div>";}'),
            array('name' => 'sORDERSTATEMAIL1','objectdata' => 'a:4:{s:8:"fromMail";s:16:"{$sConfig.sMAIL}";s:8:"fromName";s:20:"{$sConfig.sSHOPNAME}";s:7:"subject";s:38:"Your order with {config name=shopName}";s:7:"content";s:334:"Dear{if $sUser.billing_salutation eq "mr"}Mr{elseif $sUser.billing_salutation eq "ms"}Mrs{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},

The status of your order with order number {$sOrder.ordernumber} of {$sOrder.ordertime|date_format:" %d-%m-%Y"} has changed. The new status is as follows: {$sOrder.status_description}.";}'),
            array('name' => 'sORDERSTATEMAIL11','objectdata' => 'a:2:{s:7:"subject";s:22:"Order shipped in parts";s:7:"content";s:334:"Dear{if $sUser.billing_salutation eq "mr"}Mr{elseif $sUser.billing_salutation eq "ms"}Mrs{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},

The status of your order with order number {$sOrder.ordernumber} of {$sOrder.ordertime|date_format:" %d-%m-%Y"} has changed. The new status is as follows: {$sOrder.status_description}.";}'),
            array('name' => 'sORDERSTATEMAIL2','objectdata' => 'a:2:{s:7:"subject";s:36:"Your order at {config name=shopName}";s:7:"content";s:332:"Dear{if $sUser.billing_salutation eq "mr"}Mr{elseif $sUser.billing_salutation eq "ms"}Mrs{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},

The status of your order with order number{$sOrder.ordernumber} of {$sOrder.ordertime|date_format:" %d-%m-%Y"} has changed. The new status is as follows {$sOrder.status_description}.";}'),
            array('name' => 'sORDERSTATEMAIL3','objectdata' => 'a:2:{s:7:"subject";s:13:"Status change";s:7:"content";s:923:"Dear {if $sUser.billing_salutation eq "mr"}Mr{elseif $sUser.billing_salutation eq "ms"} Mrs{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},
 
The status of your order {$sOrder.ordernumber} of {$sOrder.ordertime|date_format:" %d.%m.%Y"} 
has changed. The new status is as follows: "{$sOrder.status_description}".
 
 
Information on your order:
================================== 
{foreach item=details key=position from=$sOrderDetails}
{$position+1|fill:3} {$details.articleordernumber|fill:10:" ":"..."} {$details.name|fill:30} {$details.quantity} x {$details.price|string_format:"%.2f"} {$sConfig.sCURRENCY}
{/foreach}
 
Shipping costs: {$sOrder.invoice_shipping} {$sConfig.sCURRENCY}
Net total: {$sOrder.invoice_amount_net|string_format:"%.2f"} {$sConfig.sCURRENCY}
Total amount incl. VAT: {$sOrder.invoice_amount|string_format:"%.2f"} {$sConfig.sCURRENCY}
 
Best regards,
Your team of {config name=shopName}

";}'),
            array('name' => 'sORDERSTATEMAIL4','objectdata' => 'a:2:{s:7:"subject";s:38:"Your order with {config name=shopName}";s:7:"content";s:551:"Hello {if $sUser.billing_salutation eq "mr"}Mr{elseif $sUser.billing_salutation eq "ms"}Mrs{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},
 
The order status of your order {$sOrder.ordernumber} has changed!
The order now has the following status: {$sOrder.status_description}.

You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
 
Best regards,
Your team of {config name=shopName}";}'),
            array('name' => 'sORDERSTATEMAIL5','objectdata' => 'a:2:{s:7:"subject";s:38:"Your order with {config name=shopName}";s:7:"content";s:389:"Dear{if $sUser.billing_salutation eq "mr"}Mr{elseif $sUser.billing_salutation eq "ms"}Mrs{/if} 
{$sUser.billing_firstname} {$sUser.billing_lastname},
 
The status of your order with order number {$sOrder.ordernumber} of {$sOrder.ordertime|date_format:" %d.%m.%Y"} 
has changed. The new status is as follows: {$sOrder.status_description}.
 
Best regards,
Your team of {config name=shopName}";}'),
            array('name' => 'sORDERSTATEMAIL6','objectdata' => 'a:2:{s:7:"subject";s:38:"Your order with {config name=shopName}";s:7:"content";s:552:"Hello {if $sUser.billing_salutation eq "mr"}Mr{elseif $sUser.billing_salutation eq "ms"}Mrs{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},
 
The order status of your order {$sOrder.ordernumber} has changed!
Your order now has the following status: {$sOrder.status_description}.

You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
 
Best regards,
Your team of {config name=shopName}";}'),
            array('name' => 'sORDERSTATEMAIL8','objectdata' => 'a:2:{s:7:"subject";s:38:"Your order with {config name=shopName}";s:7:"content";s:553:"Hello {if $sUser.billing_salutation eq "mr"}Mr{elseif $sUser.billing_salutation eq "ms"}Mrs{/if} {$sUser.billing_firstname} {$sUser.billing_lastname},
 
The status of your order {$sOrder.ordernumber} has changed!
The current status of your order is as follows: {$sOrder.status_description}.

You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
 
Best regards,
Your team of {config name=shopName}";}'),
            array('name' => 'sPASSWORD','objectdata' => 'a:2:{s:7:"subject";s:46:"Forgot password - Your access data for {sShop}";s:7:"content";s:127:"Hello,

Your access data for {sShopURL} is as follows:
User: {sMail}
Password: {sPassword}

Best regards

{config name=address}";}'),
            array('name' => 'sREGISTERCONFIRMATION','objectdata' => 'a:3:{s:7:"subject";s:37:"Your registration has been successful";s:7:"content";s:291:"Hello {salutation} {firstname} {lastname},
 
Thank you for your registration with our Shop.
 
You will gain access via the email address {sMAIL}
and the password you have chosen.
 
You can have your password sent to you by email anytime. 
 
Best regards
 
Your team of {config name=shopName}";s:11:"contentHtml";s:354:"<div>
Hello {salutation} {firstname} {lastname},<br/><br/>
 
Thank you for your registration with our Shop.<br/><br/>
 
You will gain access via the email address {sMAIL} and the password you have chosen.<br/><br/>
 
You can have your password sent to you by email anytime. <br/><br/>
 
Best regards<br/><br/>
 
Your team of {config name=shopName}
</div>";}'),
            array('name' => 'sTELLAFRIEND','objectdata' => 'a:2:{s:7:"subject";s:33:"{sName} recommends you {sArticle}";s:7:"content";s:189:"Hello,

{sName} has found an interesting product for you on {sShop} that you should have a look at:

{sArticle}
{sLink}

{sComment}

Best regards and see you next time

Your contact details";}'),
            array('name' => 'sVOUCHER','objectdata' => 'a:2:{s:7:"subject";s:12:"Your voucher";s:7:"content";s:268:"Hello {customer},

{user} has followed your recommendation and just ordered at {config name=shopName}.
This is why we give you a X € voucher, which you can redeem with your next order.
			
Your voucher code is as follows: XXX
			
Best regards,
{config name=shopName}";}')
        );
    }
}