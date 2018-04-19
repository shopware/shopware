<?php
class Migrations_Migration468 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        if ($modus !== self::MODUS_INSTALL) {
            return;
        }

        $sql = <<<'SQL'
UPDATE `s_export` 
SET body = '{strip}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape|htmlentities}{#S#}\n{$sArticle.description_long|strip_tags|html_entity_decode|trim|regex_replace:"#[^\\wöäüÖÄÜß\\.%&-+ ]#i":""|strip|truncate:500:"...":true|htmlentities|escape}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{$sArticle.image|image:1}{#S#}\n{$sArticle.ean|escape}{#S#}\n{if $sArticle.weight}{$sArticle.weight|escape:"number"}{" kg"}{/if}{#S#}\n{$sArticle.supplier|escape}{#S#}\n{$sArticle.suppliernumber|escape}{#S#}\nNeu{#S#}\n{$sArticle.articleID|category:" > "|escape}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\nDE::DHL:{$sArticle|@shippingcost:"prepayment":"de"}{#S#}\n{#S#}\n{$sCurrency.currency}\n{/strip}{#L#}'
WHERE `name` = 'Google Produktsuche'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '{strip}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{$sArticle.name|escape|truncate:70}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:150:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\n{$sArticle.ordernumber}{#S#}\n{$sArticle.image|image:2|escape}{#S#}\n{if $sArticle.instock}001{else}002{/if}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}\n{/strip}{#L#}'
WHERE `name` = 'Kelkoo'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '{strip}\n{$sArticle.ordernumber}{#S#}\n{$sArticle.supplier|escape}{#S#}\n{$sArticle.suppliernumber|escape}{#S#}\n{$sArticle.ean|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.articleID|category:">"|escape}{#S#}\n{$sArticle.price|escape:number}{#S#}\n{if $sArticle.purchaseunit}{$sArticle.price/$sArticle.purchaseunit*$sArticle.referenceunit|escape:number} {"\\x80"} / {$sArticle.referenceunit} {$sArticle.unit}{/if}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{$sArticle.image|image:2}{#S#}\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de"|escape:number}{#S#}\n\n{/strip}{#L#}'
WHERE `name` = 'billiger.de'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '{strip}\n{$sArticle.articleID|category:">"|escape|replace:"|":""}{#S#}\n{$sArticle.supplier|replace:"|":""}{#S#}\n{$sArticle.name|strip_tags|strip|trim|html_entity_decode|escape}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\n{#S#}\n{#S#}\n{#S#}\n{#S#}\n{$sArticle|@shippingcost:"cash":"de":"Deutsche Post Standard"|escape:"number"}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}{#S#}\n{$sArticle|@shippingcost:"debit":"de":"Deutsche Post Standard"|escape:"number"}{#S#}\n{$sArticle.articleID|link:$sArticle.name|replace:"|":""}{#S#}\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime|escape} Tage{else}10 Tage{/if}{#S#}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.image|image:2}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:300:"...":true|escape}\n{/strip}{#L#}'
WHERE `name` = 'Idealo'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '{strip}\n{$sArticle.articleID|escape}{#S#}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|replace:"|":""} {#S#}\n{$sArticle.tax}{#S#}\n{$sArticle.articleID|category:">"|escape},{$sArticle.supplier}{#S#}\n{$sArticle.weight}{#S#}\n{$sArticle.description|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|replace:"|":""|escape}{#S#}\n"{$sArticle.description_long|trim|html_entity_decode|replace:"|":"|"|replace:''"'':''""''}<p>{$sArticle.attr1|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr2|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr3|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr4|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr5|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr6|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr7|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr8|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr9|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr10|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr11|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr12|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr13|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr14|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr15|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr16|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr17|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr18|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr19|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}<p>{$sArticle.attr20|regex_replace:"/^(\\d)$/":""|regex_replace:"/^0000-00-00$/":""|strip}"{#S#}\n{$sArticle.image|image:2}{#S#}\n{$sArticle.articleID|link:$sArticle.name|replace:"|":""}{#S#}\n{if $sArticle.configurator}0{else}{$sArticle.price|escape:"number"|escape}{/if}{#S#}\n{$sArticle.pseudoprice|escape}{#S#}\nLieferzeit in Tagen: {$sArticle.shippingtime|replace:"0":"sofort"}{#S#}\n{$sArticle.topseller}{#S#}\n{if $sArticle.configurator}"-1"{else}{$sArticle.instock}{/if}{#S#}\n{$sArticle.purchaseunit}{#S#}\n{$sArticle.unit_description}{#S#}\n{$sArticle.suppliernumber}{#S#}\n{$sArticle.supplier}{#S#}\n{$sArticle.active}{#S#}\n{if $sArticle.configurator}{$sArticle.articleID|escape}{else}{/if}\n{/strip}{#L#}'
WHERE `name` = 'Yatego'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '{strip}\n{$sArticle.ordernumber|replace:"|":""}|\n{$sArticle.supplier|replace:"|":""}|\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|replace:"|":""}|\n{$sArticle.price|escape:"number"}|\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}|\n{$sArticle.articleID|link:$sArticle.name|replace:"|":""}|\n{$sArticle.image|image:0}|\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|replace:"|":""}|\n{$sArticle|@shippingcost:"cash":"de":"Deutsche Post Standard"|escape:"number"}|\n|\n{$sArticle|@shippingcost:"debit":"de":"Deutsche Post Standard"|escape:"number"}|\n|\n{$sArticle|@shippingcost:"invoice":"de":"Deutsche Post Standard"|escape:"number"}|\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}|\n{$sArticle.ean|replace:"|":""}|\n{$sArticle.weight|replace:"|":""}\n{/strip}{#L#}'
WHERE `name` = 'guenstiger.de'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '{strip}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.supplier|escape}{#S#}\n{$sArticle.name|escape}{#S#}\n{$sArticle.articleID|category:">"|escape}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.image|image:0}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}{#S#}\n{$sArticle|@shippingcost:"cash":"de":"Deutsche Post Standard"|escape:"number"}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\n{$sArticle.ean|escape}{#S#}\n{/strip}{#L#}'
WHERE `name` = 'geizhals.at'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '{strip}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.supplier|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape}{#S#}\n{$sArticle.articleID|category:">"|escape}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.image|image:0}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de"|escape:"number"}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\n{#S#}\n{/strip}{#L#}'
WHERE `name` = 'Ciao'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '{strip}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.supplier|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape}{#S#}\n{$sArticle.articleID|category:">"|escape}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.image|image:0|escape}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}{#S#}\n{$sArticle.releasedate|escape}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\n{#S#}\n{/strip}{#L#}\n\n'
WHERE `name` = 'Pangora'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '{strip}\n|\n{$sArticle.ean}|\n{$sArticle.supplier}|\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true}|\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode}|\n{$sArticle.price|escape:"number"}|\n{$sArticle.articleID|link:$sArticle.name}|\n{$sArticle.image|image:1}|\n{$sArticle.articleID|category:">"}|\n{if $sArticle.instock}Ja{else}Nein{/if}|\n{if $sArticle.instock}1-3 Werktage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}|\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}\n{/strip}{#L#}'
WHERE `name` = 'Shopping.com'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '{strip}\n{$sArticle.ordernumber|escape}{#S#}\n{$sArticle.price|escape:"number"|escape}{#S#}\n{$sArticle.articleID|category:">"|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{#F#}{if $sArticle.instock}1-3 Werktage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}{#F#}{#S#}\n{$sArticle.ean|escape}{#S#}\n{$sArticle.suppliernumber|escape}{#S#}\n{$sArticle.image|image:0|escape}{#S#}\n{$sArticle.supplier|escape}{#S#}\n{$sArticle|@shippingcost:"prepayment":"de"|escape:"number"|escape}{#S#}\n{$sArticle|@shippingcost:"cash":"de"|escape:"number"|escape}{#S#}\n{$sArticle|@shippingcost:"debit":"de"|escape:"number"|escape}{#S#}\n{""|escape}{#S#}\n{$sArticle|@shippingcost:"invoice":"de"|escape:"number"|escape}{#S#}\n{$sArticle|@shippingcost:"paypal":"de"|escape:"number"|escape}{#S#}\n{/strip}{#L#}'
WHERE `name` = 'evendi.de'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '{strip}\n{$sArticle.ordernumber}{#S#}\n{$sArticle.articleID|category:">"|escape}{#S#}\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape}{#S#}\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|escape}{#S#}\n{$sArticle.price|escape:"number"}{#S#}\n{$sArticle.image|image:2|escape}{#S#}\n{$sArticle.articleID|link:$sArticle.name|escape}{#S#}\n{/strip}{#L#}'
WHERE `name` = 'affili.net'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '<item> \n    <g:id>{$sArticle.articleID|escape}</g:id>\n	<title>{$sArticle.name|strip_tags|strip|truncate:80:"...":true|escape}</title>\n	<description>{$sArticle.description_long|strip_tags|strip|truncate:900:"..."|escape}</description>\n	<g:google_product_category>Wählen Sie hier Ihre Google Produkt-Kategorie</g:google_product_category>\n	<g:product_type>{$sArticle.articleID|category:" > "|escape}</g:product_type>\n	<link>{$sArticle.articleID|link:$sArticle.name|escape}</link>\n	<g:image_link>{$sArticle.image|image:1}</g:image_link>\n	<g:condition>neu</g:condition>\n	<g:availability>{if $sArticle.esd}bestellbar{elseif $sArticle.instock>0}bestellbar{elseif $sArticle.releasedate && $sArticle.releasedate|strtotime > $smarty.now}vorbestellt{elseif $sArticle.shippingtime}bestellbar{else}nicht auf lager{/if}</g:availability>\n	<g:price>{$sArticle.price|format:"number"}</g:price>\n	<g:brand>{$sArticle.supplier|escape}</g:brand>\n	<g:gtin>{$sArticle.suppliernumber|replace:"|":""}</g:gtin>\n	<g:mpn>{$sArticle.suppliernumber|escape}</g:mpn>\n	<g:shipping>\n       <g:country>DE</g:country>\n       <g:service>Standard</g:service>\n       <g:price>{$sArticle|@shippingcost:"prepayment":"de"|escape:number}</g:price>\n    </g:shipping>\n  {if $sArticle.changed}<pubDate>{$sArticle.changed|date_format:"%a, %d %b %Y %T %Z"}</pubDate>{/if}		\n</item>'
WHERE `name` = 'Google Produktsuche XML'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '{strip}\n{$sArticle.ordernumber|replace:"|":""}|\n{$sArticle.supplier|replace:"|":""}|\n{$sArticle.name|strip_tags|strip|truncate:80:"...":true|replace:"|":""}|\n{$sArticle.price|escape:"number"}|\n{if $sArticle.instock}2 Tage{elseif $sArticle.shippingtime}{$sArticle.shippingtime} Tage{else}10 Tage{/if}|\n{$sArticle.articleID|link:$sArticle.name|replace:"|":""}|\n{$sArticle.image|image:0}|\n{$sArticle.description_long|strip_tags|strip|trim|truncate:900:"...":true|html_entity_decode|replace:"|":""}|\n{$sArticle|@shippingcost:"cash":"de":"Deutsche Post Standard"|escape:"number"}|\n|\n{$sArticle|@shippingcost:"debit":"de":"Deutsche Post Standard"|escape:"number"}|\n|\n{$sArticle|@shippingcost:"invoice":"de":"Deutsche Post Standard"|escape:"number"}|\n{$sArticle|@shippingcost:"prepayment":"de":"Deutsche Post Standard"|escape:"number"}|\n{$sArticle.ean|replace:"|":""}|\n{$sArticle.weight|replace:"|":""}\n{/strip}{#L#}'
WHERE `name` = 'preissuchmaschine.de'
AND dirty = 0;
SQL;
        $this->addSql($sql);

        $sql = <<<'SQL'
UPDATE `s_export`
SET body = '<item> \n	<title>{$sArticle.name|strip_tags|htmlspecialchars_decode|strip|escape}</title>\n	<guid>{$sArticle.articleID|link:$sArticle.name|escape}</guid>\n	<link>{$sArticle.articleID|link:$sArticle.name}</link>\n	<description>{if $sArticle.image}\n		<a href="{$sArticle.articleID|link:$sArticle.name}" style="border:0 none;">\n			<img src="{$sArticle.image|image:0}" align="right" style="padding: 0pt 0pt 12px 12px; float: right;" />\n		</a>\n{/if}\n		{$sArticle.description_long|strip_tags|regex_replace:"/[^\\wöäüÖÄÜß .?!,&:%;\\-\\"'']/i":""|trim|truncate:900:"..."|escape}\n	</description>\n	<category>{$sArticle.articleID|category:">"|htmlspecialchars_decode|escape}</category>\n{if $sArticle.changed} 	{assign var="sArticleChanged" value=$sArticle.changed|strtotime}<pubDate>{"r"|date:$sArticleChanged}</pubDate>{"rn"}{/if}\n</item>{#L#}'
WHERE `name` = 'RSS Feed-Template'
AND dirty = 0;
SQL;
        $this->addSql($sql);
    }
}


