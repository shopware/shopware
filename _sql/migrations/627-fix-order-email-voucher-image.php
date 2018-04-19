<?php

class Migrations_Migration627 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {

        $content = <<<'CONTENT'
{include file="string:{config name=emailheaderhtml}"}
<br/><br/>
<p>
Hallo {$billingaddress.firstname} {$billingaddress.lastname},<br/><br/>

vielen Dank fuer Ihre Bestellung bei {config name=shopName} (Nummer: {$sOrderNumber}) am {$sOrderDay|date:"DATE_MEDIUM"} um {$sOrderTime|date:"TIME_SHORT"}.
<br/>
<br/>
<strong>Informationen zu Ihrer Bestellung:</strong></p>
  <table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:10px;">
    <tr>
      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Artikel</strong></td>
      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>
      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Art-Nr.</strong></td>
      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Menge</strong></td>
      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Preis</strong></td>
      <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Summe</strong></td>
    </tr>

    {foreach item=details key=position from=$sOrderDetails}
    <tr>
      <td rowspan="2" style="border-bottom:1px solid #cccccc;">{if $details.image.src.0 && $details.modus != 2}<img style="height: 57px;" height="57" src="{$details.image.src.0}" alt="{$details.articlename}" />{else} {/if}</td>
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
    Versandkosten: {$sShippingCosts}<br/>
    Gesamtkosten Netto: {$sAmountNet}<br/>
    {if !$sNet}
    Gesamtkosten Brutto: {$sAmount}<br/>
    {/if}
  <br/>
  <br/>
    <strong>Gewählte Zahlungsart:</strong> {$additional.payment.description}<br/>
    {include file="string:{$additional.payment.additionaldescription}"}
    {if $additional.payment.name == "debit"}
    Ihre Bankverbindung:<br/>
    Kontonr: {$sPaymentTable.account}<br/>
    BLZ:{$sPaymentTable.bankcode}<br/>
    Wir ziehen den Betrag in den nächsten Tagen von Ihrem Konto ein.<br/>
    {/if}
  <br/>
  <br/>
    {if $additional.payment.name == "prepayment"}
    Unsere Bankverbindung:<br/>
    {config name=bankAccount}
    {/if}
  <br/>
  <br/>
    <strong>Gewählte Versandart:</strong> {$sDispatch.name}<br/>{$sDispatch.description}
</p>
<p>
  {if $sComment}
    <strong>Ihr Kommentar:</strong><br/>
    {$sComment}<br/>
  {/if}
  <br/>
  <br/>
    <strong>Rechnungsadresse:</strong><br/>
    {$billingaddress.company}<br/>
    {$billingaddress.firstname} {$billingaddress.lastname}<br/>
    {$billingaddress.street}<br/>
    {$billingaddress.zipcode} {$billingaddress.city}<br/>
    {$billingaddress.phone}<br/>
    {$additional.country.countryname}<br/>
  <br/>
  <br/>
    <strong>Lieferadresse:</strong><br/>
    {$shippingaddress.company}<br/>
    {$shippingaddress.firstname} {$shippingaddress.lastname}<br/>
    {$shippingaddress.street}<br/>
    {$shippingaddress.zipcode} {$shippingaddress.city}<br/>
    {$additional.countryShipping.countryname}<br/>
  <br/>
    {if $billingaddress.ustid}
    Ihre Umsatzsteuer-ID: {$billingaddress.ustid}<br/>
    Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland<br/>
    bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit.<br/>
    {/if}
  <br/>
  <br/>
    Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung. Sie erreichen uns wie folgt: <br/>{config name=address}
</p>
<br/><br/>
{include file="string:{config name=emailfooterhtml}"}
CONTENT;

        $statement = $this->connection->prepare('UPDATE `s_core_config_mails` SET `contentHTML` = ? WHERE `name` = "sORDER" AND dirty = 0');
        $statement->execute(array($content));
    }
}