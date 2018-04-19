<?php
class Migrations_Migration404 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
            UPDATE s_export
                SET body = '<item> \n	<title>{$sArticle.name|strip_tags|htmlspecialchars_decode|strip|escape}</title>\n	<guid>{$sArticle.articleID|link:$sArticle.name|escape}</guid>\n	<link>{$sArticle.articleID|link:$sArticle.name}</link>\n	<description>{if $sArticle.image}\n		<a href="{$sArticle.articleID|link:$sArticle.name}" style="border:0 none;">\n			<img src="{$sArticle.image|image:3}" align="right" style="padding: 0pt 0pt 12px 12px; float: right;" />\n		</a>\n{/if}\n		{$sArticle.description_long|strip_tags|regex_replace:"/[^\\wöäüÖÄÜß .?!,&:%;\\-\\"'']/i":""|trim|truncate:900:"..."|escape}\n	</description>\n	<category>{$sArticle.articleID|category:">"|htmlspecialchars_decode|escape}</category>\n{if $sArticle.changed} 	{assign var="sArticleChanged" value=$sArticle.changed|strtotime}<pubDate>{"r"|date:$sArticleChanged}</pubDate>{"rn"}{/if}\n</item>{#L#}'
            WHERE s_export.name = 'RSS Feed-Template'
                AND body = '<item> \n	<title>{$sArticle.name|strip_tags|htmlspecialchars_decode|strip|escape}</title>\n	<guid>{$sArticle.articleID|link:$sArticle.name|escape}</guid>\n	<link>{$sArticle.articleID|link:$sArticle.name}</link>\n	<description>{if $sArticle.image}\n		<a href="{$sArticle.articleID|link:$sArticle.name}" style="border:0 none;">\n			<img src="{$sArticle.image|image:3}" align="right" style="padding: 0pt 0pt 12px 12px; float: right;" />\n		</a>\n{/if}\n		{$sArticle.description_long|strip_tags|regex_replace:"/[^\\wöäüÖÄÜß .?!,&:%;\\-\\"'']/i":""|trim|truncate:900:"..."|escape}\n	</description>\n	<category>{$sArticle.articleID|category:">"|htmlspecialchars_decode|escape}</category>\n{if $sArticle.changed} 	{assign var="sArticleChanged" value=$sArticle.changed|strtotime}<pubDate>{"r"|date:$sArticleChanged}</pubDate>{"rn"}{/if}\n</item>{#L#}'
        ;
EOD;

        $this->addSql($sql);
    }
}

