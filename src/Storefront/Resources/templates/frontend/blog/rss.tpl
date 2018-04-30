<?xml version="1.0" encoding="{encoding}" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <atom:link href="{$sCategoryContent.rssFeed}" rel="self" type="application/rss+xml" />
        <title>{block name='frontend_atom_title'}{s name="BlogRssFeedHeader"}{$sCategoryContent.description}{/s}{/block}</title>
        <link>{url controller='index'}</link>
        <description>{$sShopname|escapeHtml} - {$sCategoryContent.description}</description>
        <language>de-de</language>
        <lastBuildDate>{time()|date:rss}</lastBuildDate>
    {foreach from=$sBlogArticles item=sArticle key=key name="counter"}
        <item>
            <title>{block name='frontend_blog_listing_rss_title'}{$sArticle.title|strip_tags|strip|truncate:80:"...":true|escape}{/block}</title>
            <guid>{block name='frontend_blog_listing_rss_guid'}{url controller=blog action=detail sCategory=$sArticle.categoryId blogArticle=$sArticle.id}{/block}</guid>
            <link>{block name='frontend_blog_listing_rss_link'}{url controller=blog action=detail sCategory=$sArticle.categoryId blogArticle=$sArticle.id}{/block}</link>
            <description>{block name='frontend_blog_listing_rss_description'}{$sArticle.shortDescription|strip_tags|strip|truncate:280:"...":true|escape}{/block}</description>
            <category>{block name='frontend_blog_listing_rss_category'}{$sCategoryContent.description}{/block}</category>
            {if $sArticle.displayDate}
                <pubDate>{block name='frontend_blog_listing_rss_date'}{$sArticle.displayDate|date:rss}{/block}</pubDate>
            {/if}
        </item>
    {/foreach}
    </channel>
</rss>
