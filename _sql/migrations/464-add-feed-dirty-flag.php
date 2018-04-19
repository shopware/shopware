<?php

class Migrations_Migration464 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $this->addSql('ALTER TABLE `s_export` ADD `dirty` INT(1) NULL ;');

        if ($modus === self::MODUS_INSTALL) {
            $this->addSql('UPDATE `s_export` SET `dirty` = 0;');

            return;
        } else {
            $this->addSql('UPDATE `s_export` SET `dirty` = 1;');
        }

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\nid{#S#}\ntitel{#S#}\nbeschreibung{#S#}\nlink{#S#}\nbild_url{#S#}\nean{#S#}\ngewicht{#S#}\nmarke{#S#}\nmpn{#S#}\nzustand{#S#}\nproduktart{#S#}\npreis{#S#}\nversand{#S#}\nstandort{#S#}\nwährung\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape|htmlentities}{#S#}\n{$sArticle.description_long|strip_tags|html_entity_decode|trim|regex_replace:"#[^\\wöäüÖÄÜß\\.%&-+ ]#i":""|strip|truncate:500:"...":true|htmlentities|escape}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{$sArticle.image|image:4}{#S#}\n{$sArticle.ean|escape}{#S#}\n{if $sArticle.weight}{$sArticle.weight|escape:"number"}{" kg"}{/if}{#S#}\n{$sArticle.supplier|escape}{#S#}\n{$sArticle.suppliernumber|escape}{#S#}\nNeu{#S#}\n{$sArticle.articleID|category:" > "|escape}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\nDE::DHL:{$sArticle|@shippingcost:"prepayment":"de"}{#S#}\n{#S#}\n{$sCurrency.currency}\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'Google Produktsuche';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\nurl{#S#}\ntitle{#S#}\ndescription{#S#}\nprice{#S#}\nofferid{#S#}\nimage{#S#}\navailability{#S#}\ndeliverycost\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{$sArticle.name|escape|truncate:70}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:150:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\n{$sArticle.ordernumber}{#S#}\n{$sArticle.image|image:5|escape}{#S#}\n{if $sArticle.instock}001{else}002{/if}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'Kelkoo';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\naid{#S#}\nbrand{#S#}\nmpnr{#S#}\nean{#S#}\nname{#S#}\ndesc{#S#}\nshop_cat{#S#}\nprice{#S#}\nppu{#S#}\nlink{#S#}\nimage{#S#}\ndlv_time{#S#}\ndlv_cost{#S#}\npzn\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.ordernumber}{#S#}\n{$sArticle.supplier|escape}{#S#}\n{$sArticle.suppliernumber|escape}{#S#}\n{$sArticle.ean|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.articleID|category:">"|escape}{#S#}\n{$sArticle.price|escape:number}{#S#}\n{if $sArticle.purchaseunit}{$sArticle.price/$sArticle.purchaseunit*$sArticle.referenceunit|escape:number} {"\\x80"} / {$sArticle.referenceunit} {$sArticle.unit}{/if}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{$sArticle.image|image:5}{#S#}\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de"|escape:number}{#S#}\n\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'billiger.de';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\nKategorie{#S#}\nHersteller{#S#}\nProduktbezeichnung{#S#}\nPreis{#S#}\nHersteller-Artikelnummer{#S#}\nEAN{#S#}\nPZN{#S#}\nISBN{#S#}\nVersandkosten Nachnahme{#S#}\nVersandkosten Vorkasse{#S#}\nVersandkosten Bankeinzug{#S#}\nDeeplink{#S#}\nLieferzeit{#S#}\nArtikelnummer{#S#}\nLink Produktbild{#S#}\nProdukt Kurztext\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.articleID|category:">"|escape|replace:"|":""}{#S#}\n{$sArticle.supplier|replace:"|":""}{#S#}\n{$sArticle.name|strip_tags|strip|trim|html_entity_decode|escape}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\n{#S#}\n{#S#}\n{#S#}\n{#S#}\n{$sArticle|@shippingcost:"cash":"de":"Deutsche Post Standard"|escape:"number"}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}{#S#}\n{$sArticle|@shippingcost:"debit":"de":"Deutsche Post Standard"|escape:"number"}{#S#}\n{$sArticle.articleID|link:$sArticle.name|replace:"|":""}{#S#}\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime|escape} Tage{else}10 Tage{/if}{#S#}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.image|image:5}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:300:"...":true|escape}\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'Idealo';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}foreign_id{#S#}\narticle_nr{#S#}\ntitle{#S#}\ntax{#S#}\ncategories{#S#}\nunits{#S#}\nshort_desc{#S#}\nlong_desc{#S#}\npicture{#S#}\nurl{#S#}\nprice{#S#}\nprice_uvp{#S#}\ndelivery_date{#S#}\ntop_offer{#S#}\nstock{#S#}\npackage_size{#S#}\nquantity_unit{#S#}\nmpn{#S#}\nmanufacturer{#S#}\nstatus{#S#}\nvariants\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.articleID|escape}{#S#}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|replace:"|":""} {#S#}\n{$sArticle.tax}{#S#}\n{$sArticle.articleID|category:">"|escape},{$sArticle.supplier}{#S#}\n{$sArticle.weight}{#S#}\n{$sArticle.description|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|replace:"|":""|escape}{#S#}\n"{$sArticle.description_long|trim|html_entity_decode|replace:"|":"|"|replace:''"'':''""''}<p>{$sArticle.attr1|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr2|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr3|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr4|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr5|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr6|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr7|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr8|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr9|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr10|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr11|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr12|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr13|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr14|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr15|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr16|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr17|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr18|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr19|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr20|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}"{#S#}\n{$sArticle.image|image:5}{#S#}\n{$sArticle.articleID|link:$sArticle.name|replace:"|":""}{#S#}\n{if $sArticle.configurator}0{else}{$sArticle.price|escape:"number"|escape}{/if}{#S#}\n{$sArticle.pseudoprice|escape}{#S#}\nLieferzeit in Tagen: {$sArticle.shippingtime|replace:"0":"sofort"}{#S#}\n{$sArticle.topseller}{#S#}\n{if $sArticle.configurator}"-1"{else}{$sArticle.instock}{/if}{#S#}\n{$sArticle.purchaseunit}{#S#}\n{$sArticle.unit_description}{#S#}\n{$sArticle.suppliernumber}{#S#}\n{$sArticle.supplier}{#S#}\n{$sArticle.active}{#S#}\n{if $sArticle.configurator}{$sArticle.articleID|escape}{else}{/if}\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'Yatego';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\nHersteller|\nProduktbezeichnung|\nProduktbeschreibung|\nPreis|\nVerfügbarkeit|\nEAN|\nHersteller AN|\nDeeplink|\nArtikelnummer|\nDAN_Ingram|\nVersandkosten Nachnahme|\nVersandkosten Vorkasse|\nVersandkosten Kreditkarte|\nVersandkosten Bankeinzug\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.supplier|replace:"|":""}|\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|replace:"|":""}|\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|replace:"|":""}|\n{$sArticle.price|escape:"number"}|\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}|\n{$sArticle.ean|replace:"|":""}|\n{$sArticle.suppliernumber|replace:"|":""}|\n{$sArticle.articleID|link:$sArticle.name|replace:"|":""}|\n{$sArticle.ordernumber|replace:"|":""}|\n|\n{$sArticle|@shippingcost:"cash":"de":"Deutsche Post Standard"|escape:"number"}|\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}|\n{$sArticle|@shippingcost:"credituos":"de":"Deutsche Post Standard"|escape:"number"}|\n{$sArticle|@shippingcost:"debit":"de":"Deutsche Post Standard"|escape:"number"}|\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'schottenland.de';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\nBestellnummer|\nHersteller|\nBezeichnung|\nPreis|\nLieferzeit|\nProduktLink|\nFotoLink|\nBeschreibung|\nVersandNachnahme|\nVersandKreditkarte|\nVersandLastschrift|\nVersandBankeinzug|\nVersandRechnung|\nVersandVorkasse|\nEANCode|\nGewicht\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.ordernumber|replace:"|":""}|\n{$sArticle.supplier|replace:"|":""}|\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|replace:"|":""}|\n{$sArticle.price|escape:"number"}|\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}|\n{$sArticle.articleID|link:$sArticle.name|replace:"|":""}|\n{$sArticle.image|image:2}|\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|replace:"|":""}|\n{$sArticle|@shippingcost:"cash":"de":"Deutsche Post Standard"|escape:"number"}|\n|\n{$sArticle|@shippingcost:"debit":"de":"Deutsche Post Standard"|escape:"number"}|\n|\n{$sArticle|@shippingcost:"invoice":"de":"Deutsche Post Standard"|escape:"number"}|\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}|\n{$sArticle.ean|replace:"|":""}|\n{$sArticle.weight|replace:"|":""}\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'guenstiger.de';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\nID{#S#}\nHersteller{#S#}\nArtikelbezeichnung{#S#}\nKategorie{#S#}\nBeschreibungsfeld{#S#}\nBild{#S#}\nUrl{#S#}\nLagerstandl{#S#}\nVersandkosten{#S#}\nVersandkostenNachname{#S#}\nPreis{#S#}\nEAN{#S#}\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.supplier|escape}{#S#}\n{$sArticle.name|escape}{#S#}\n{$sArticle.articleID|category:">"|escape}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.image|image:3}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}{#S#}\n{$sArticle|@shippingcost:"cash":"de":"Deutsche Post Standard"|escape:"number"}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\n{$sArticle.ean|escape}{#S#}\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'geizhals.at';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\nOffer ID{#S#}\nBrand{#S#}\nProduct Name{#S#}\nCategory{#S#}\nDescription{#S#}\nImage URL{#S#}\nProduct URL{#S#}\nDelivery{#S#}\nShippingCost{#S#}\nPrice{#S#}\nProduct ID{#S#}\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.supplier|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape}{#S#}\n{$sArticle.articleID|category:">"|escape}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.image|image:3}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de"|escape:"number"}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\n{#S#}\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'Ciao';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\noffer-id{#S#}\nmfname{#S#}\nlabel{#S#}\nmerchant-category{#S#}\ndescription{#S#}\nimage-url{#S#}\noffer-url{#S#}\nships-in{#S#}\nrelease-date{#S#}\ndelivery-charge{#S#}\nprices	old-prices{#S#}\nproduct-id{#S#}\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.supplier|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape}{#S#}\n{$sArticle.articleID|category:">"|escape}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.image|image:3|escape}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}{#S#}\n{$sArticle.releasedate|escape}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\n{#S#}\n{/strip}{#L#}\n\n'
AND footer = ''
AND `name` = 'Pangora';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\nMPN|\nEAN|\nHersteller|\nProduktname|\nProduktbeschreibung|\nPreis|\nProdukt-URL|\nProduktbild-URL|\nKategorie|\nVerfügbar|\nVerfügbarkeitsdetails|\nVersandkosten\n{/strip}{#L#}'
AND body = '{strip}\n|\n{$sArticle.ean}|\n{$sArticle.supplier}|\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true}|\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode}|\n{$sArticle.price|escape:"number"}|\n{$sArticle.articleID|link:$sArticle.name}|\n{$sArticle.image|image:4}|\n{$sArticle.articleID|category:">"}|\n{if $sArticle.instock}Ja{else}Nein{/if}|\n{if $sArticle.instock}1-3 Werktage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}|\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'Shopping.com';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\nean{#S#}\ncondition{#S#}\nprice{#S#}\ncomment{#S#}\noffer_id{#S#}\nlocation{#S#}\ncount{#S#}\ndelivery_time{#S#}\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.ean|escape}{#S#}\n100{#S#}\n{$sArticle.price*100}{#S#}\n{#S#}\n{$sArticle.ordernumber|escape}{#S#}\n{#S#}\n{#S#}\n{if $sArticle.instock}b{else}d{/if}{#S#}\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'Hitmeister';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\nEindeutige Händler-Artikelnummer{#S#}\nPreis in Euro{#S#}\nKategorie{#S#}\nProduktbezeichnung{#S#}\nProduktbeschreibung{#S#}\nLink auf Detailseite{#S#}\nLieferzeit{#S#}\nEAN-Nummer{#S#}\nHersteller-Artikelnummer{#S#}\nLink auf Produktbild{#S#}\nHersteller{#S#}\nVersandVorkasse{#S#}\nVersandNachnahme{#S#}\nVersandLastschrift{#S#}\nVersandKreditkarte{#S#}\nVersandRechnung{#S#}\nVersandPayPal\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.price|escape:"number"|escape}{#S#}\n{$sArticle.articleID|category:">"|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{#F#}{if $sArticle.instock}1-3 Werktage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}{#F#}{#S#}\n{$sArticle.ean|escape}{#S#}\n{$sArticle.suppliernumber|escape}{#S#}\n{$sArticle.image|image:2|escape}{#S#}\n{$sArticle.supplier|escape}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de"|escape:"number"|escape}{#S#}\n{$sArticle|@shippingcost:"cash":"de"|escape:"number"|escape}{#S#}\n{$sArticle|@shippingcost:"debit":"de"|escape:"number"|escape}{#S#}\n{""|escape}{#S#}\n{$sArticle|@shippingcost:"invoice":"de"|escape:"number"|escape}{#S#}\n{$sArticle|@shippingcost:"paypal":"de"|escape:"number"|escape}{#S#}\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'evendi.de';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\nart_number{#S#}\ncategory{#S#}\ntitle{#S#}\ndescription{#S#}\nprice{#S#}\nimg_url{#S#}\ndeeplink1{#S#}\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.ordernumber}{#S#}\n{$sArticle.articleID|category:">"|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\n{$sArticle.image|image:5|escape}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'affili.net';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '<?xml version="1.0" encoding="UTF-8" ?>\n<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0" xmlns:atom="http://www.w3.org/2005/Atom">\n<channel>\n	<atom:link href="http://{$sConfig.sBASEPATH}/engine/connectors/export/{$sSettings.id}/{$sSettings.hash}/{$sSettings.filename}" rel="self" type="application/rss+xml" />\n	<title>{$sConfig.sSHOPNAME}</title>\n	<description>Beschreibung im Header hinterlegen</description>\n	<link>http://{$sConfig.sBASEPATH}</link>\n	<language>DE</language>\n	<image>\n		<url>http://{$sConfig.sBASEPATH}/templates/_default/frontend/_resources/images/logo.jpg</url>\n		<title>{$sConfig.sSHOPNAME}</title>\n		<link>http://{$sConfig.sBASEPATH}</link>\n	</image>'
AND body = '<item> \n    <g:id>{$sArticle.articleID|escape}</g:id>\n	<title>{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape}</title>\n	<description>{$sArticle.description_long|strip_tags|strip|truncate:900:"..."|escape}</description>\n	<g:google_product_category>Wählen Sie hier Ihre Google Produkt-Kategorie</g:google_product_category>\n	<g:product_type>{$sArticle.articleID|category:" > "|escape}</g:product_type>\n	<link>{$sArticle.articleID|link:$sArticle.name|escape}</link>\n	<g:image_link>{$sArticle.image|image:4}</g:image_link>\n	<g:condition>neu</g:condition>\n	<g:availability>{if $sArticle.esd}bestellbar{elseif $sArticle.instock>0}bestellbar{elseif $sArticle.releasedate && $sArticle.releasedate|strtotime > $smarty.now}vorbestellt{elseif $sArticle.shippingtime}bestellbar{else}nicht auf lager{/if}</g:availability>\n	<g:price>{$sArticle.price|format:"number"}</g:price>\n	<g:brand>{$sArticle.supplier|escape}</g:brand>\n	<g:gtin>{$sArticle.suppliernumber|replace:"|":""}</g:gtin>\n	<g:mpn>{$sArticle.suppliernumber|escape}</g:mpn>\n	<g:shipping>\n       <g:country>DE</g:country>\n       <g:service>Standard</g:service>\n       <g:price>{$sArticle|@shippingcost:"prepayment":"de"|escape:number}</g:price>\n    </g:shipping>\n  {if $sArticle.changed}<pubDate>{$sArticle.changed|date_format:"%a, %d %b %Y %T %Z"}</pubDate>{/if}		\n</item>'
AND footer = '</channel>\n</rss>'
AND `name` = 'Google Produktsuche XML';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '{strip}\nBestellnummer|\nHersteller|\nBezeichnung|\nPreis|\nLieferzeit|\nProduktLink|\nFotoLink|\nBeschreibung|\nVersandNachnahme|\nVersandKreditkarte|\nVersandLastschrift|\nVersandBankeinzug|\nVersandRechnung|\nVersandVorkasse|\nEANCode|\nGewicht\n{/strip}{#L#}'
AND body = '{strip}\n{$sArticle.ordernumber|replace:"|":""}|\n{$sArticle.supplier|replace:"|":""}|\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|replace:"|":""}|\n{$sArticle.price|escape:"number"}|\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}|\n{$sArticle.articleID|link:$sArticle.name|replace:"|":""}|\n{$sArticle.image|image:2}|\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|replace:"|":""}|\n{$sArticle|@shippingcost:"cash":"de":"Deutsche Post Standard"|escape:"number"}|\n|\n{$sArticle|@shippingcost:"debit":"de":"Deutsche Post Standard"|escape:"number"}|\n|\n{$sArticle|@shippingcost:"invoice":"de":"Deutsche Post Standard"|escape:"number"}|\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}|\n{$sArticle.ean|replace:"|":""}|\n{$sArticle.weight|replace:"|":""}\n{/strip}{#L#}'
AND footer = ''
AND `name` = 'preissuchmaschine.de';
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export` SET dirty = 0
WHERE header = '<?xml version="1.0" encoding="UTF-8" ?>\n<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">\n<channel>\n	<atom:link href="http://{$sConfig.sBASEPATH}/engine/connectors/export/{$sSettings.id}/{$sSettings.hash}/{$sSettings.filename}" rel="self" type="application/rss+xml" />\n	<title>{$sConfig.sSHOPNAME}</title>\n	<description>Shopbeschreibung ...</description>\n	<link>http://{$sConfig.sBASEPATH}</link>\n	<language>{$sLanguage.isocode}-{$sLanguage.isocode}</language>\n	<image>\n		<url>http://{$sConfig.sBASEPATH}/templates/0/de/media/img/default/store/logo.gif</url>\n		<title>{$sConfig.sSHOPNAME}</title>\n		<link>http://{$sConfig.sBASEPATH}</link>\n	</image>{#L#}'
AND body = '<item> \n	<title>{$sArticle.name|strip_tags|htmlspecialchars_decode|strip|escape}</title>\n	<guid>{$sArticle.articleID|link:$sArticle.name|escape}</guid>\n	<link>{$sArticle.articleID|link:$sArticle.name}</link>\n	<description>{if $sArticle.image}\n		<a href="{$sArticle.articleID|link:$sArticle.name}" style="border:0 none;">\n			<img src="{$sArticle.image|image:3}" align="right" style="padding: 0pt 0pt 12px 12px; float: right;" />\n		</a>\n{/if}\n		{$sArticle.description_long|strip_tags|regex_replace:"/[^\\wöäüÖÄÜß .?!,&:%;\\-\\"'']/i":""|trim|truncate:900:"..."|escape}\n	</description>\n	<category>{$sArticle.articleID|category:">"|htmlspecialchars_decode|escape}</category>\n{if $sArticle.changed} 	{assign var="sArticleChanged" value=$sArticle.changed|strtotime}<pubDate>{"r"|date:$sArticleChanged}</pubDate>{"rn"}{/if}\n</item>{#L#}'
AND footer = '</channel>\n</rss>'
AND `name` = 'RSS Feed-Template';
SQL;
        $this->addSql($sql);
    }
}


