<?xml version="1.0" encoding="{encoding}" ?>
<feed xmlns="http://www.w3.org/2005/Atom">
<link href="{$sCategoryContent.atomFeed|escape}" rel="self" type="application/atom+xml" />
<author>
    <name>{$sShopname|escapeHtml}</name>
</author>
<title>{block name='frontend_listing_atom_title'}{$sCategoryContent.description|escape:'hexentity'}{/block}</title>
<id>{$sCategoryContent.rssFeed|escape:'hexentity'}</id>
<updated>{time()|date:atom}</updated>
{foreach $sArticles as $sArticle}
    {block name='frontend_listing_atom_entry'}
        <entry>
            <title type="text">{block name='frontend_listing_atom_article_title'}{$sArticle.articleName|strip_tags|strip|truncate:80:"...":true|escape}{/block}</title>
            <id>{block name='frontend_listing_atom_article_name'}{$sArticle.linkDetails|escape}{/block}</id>
            <link href="{block name='frontend_listing_atom_link'}{$sArticle.linkDetails|escape}{/block}"/>
            <summary type="html">
            <![CDATA[
                {block name='frontend_listing_atom_short_description'}
                {if $sArticle.description}
                    {$sArticle.description|strip_tags|strip|truncate:280:"...":true|escape}
                {else}
                    {$sArticle.description_long|strip_tags|strip|truncate:280:"...":true|escape}
                {/if}{/block}
            ]]>
            </summary>
            <content type="html">
            <![CDATA[
                {$sArticle.description_long|strip_tags|escape}
            ]]>
            </content>
            <updated>{if $sArticle.changetime}{$sArticle.changetime|date:atom}{else}{$sArticle.datum|date:atom}{/if}</updated>
        </entry>
    {/block}
{/foreach}
</feed>
