<?xml version="1.0" encoding="{encoding}" ?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <link href="{$sCategoryContent.atomFeed|escape}" rel="self" type="application/atom+xml" />
    <author>
        <name>{$sShopname|escapeHtml}</name>
    </author>
    <title>{block name='frontend_listing_atom_title'}{s name="BlogAtomFeedHeader"}{$sCategoryContent.description|escape}{/s}{/block}</title>
    <id>{$sCategoryContent.rssFeed|escape}</id>
    <updated>{time()|date:atom}</updated>
{foreach from=$sBlogArticles item=sArticle key=key name="counter"}
    {block name='frontend_listing_atom_entry'}
        <entry>
            <title type="text">{block name='frontend_listing_atom_article_title'}{$sArticle.title|strip_tags|strip|truncate:80:"...":true|escape}{/block}</title>
            <id>{block name='frontend_listing_atom_article_name'}{url controller=blog action=detail sCategory=$sArticle.categoryId blogArticle=$sArticle.id}{/block}</id>
            <link href="{block name='frontend_listing_atom_link'}{url controller=blog action=detail sCategory=$sArticle.categoryId blogArticle=$sArticle.id}{/block}"/>
            <summary type="html">
                <![CDATA[
                {block name='frontend_listing_atom_short_description'}
                    {if $sArticle.shortDescription}
                        {$sArticle.shortDescription|strip_tags|strip|truncate:280:"...":true|escape}
                        {else}
                        {$sArticle.description|strip_tags|strip|truncate:280:"...":true|escape}
                    {/if}{/block}
                ]]>
            </summary>
            <content type="html">
                <![CDATA[
                {$sArticle.description|strip_tags|escape}
                ]]>
            </content>

            {if $sArticle.displayDate}
                <updated>{$sArticle.displayDate|date:atom}</updated>
            {/if}
        </entry>

    {/block}
{/foreach}
</feed>
