<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0">
{strip}
    {block name="frontend_sitemap_mobile_xml_homepage"}
        {include file="frontend/sitemap_mobile_xml/entry.tpl" urlParams = ['controller' => 'index']}
    {/block}

    {block name="frontend_sitemap_mobile_xml_categories"}
        {foreach $sitemap.categories as $category}
            {if $category.show}
                {include file="frontend/sitemap_mobile_xml/entry.tpl" urlParams = $category.urlParams lastmod = $category.changed}
            {/if}
        {/foreach}
    {/block}

    {block name="frontend_sitemap_mobile_xml_articles"}
        {foreach $sitemap.articles as $article}
            {include file="frontend/sitemap_mobile_xml/entry.tpl" urlParams = $article.urlParams lastmod = $article.changed}
        {/foreach}
    {/block}
    {block name="frontend_sitemap_mobile_xml_blogs"}
        {foreach $sitemap.blogs as $blog}
            {include file="frontend/sitemap_mobile_xml/entry.tpl" urlParams = $blog.urlParams lastmod = $blog.changed}
        {/foreach}
    {/block}
    {block name="frontend_sitemap_mobile_xml_custom_pages"}
        {foreach $sitemap.customPages as $customPage}
            {if $customPage.show}
                {include file="frontend/sitemap_mobile_xml/entry.tpl" urlParams = $customPage.urlParams lastmod = $customPage.changed}
            {/if}
        {/foreach}
    {/block}
    {block name="frontend_sitemap_mobile_xml_suppliers"}
        {foreach $sitemap.suppliers as $supplier}
            {include file="frontend/sitemap_mobile_xml/entry.tpl" urlParams = $supplier.urlParams lastmod = $supplier.changed}
        {/foreach}
    {/block}
    {block name="frontend_sitemap_mobile_xml_landingpages"}
        {foreach $sitemap.landingPages as $landingPage}
            {if $landingPage.show}
                {include file="frontend/sitemap_mobile_xml/entry.tpl" urlParams = $landingPage.urlParams lastmod = $landingPage.0.modified}
            {/if}
        {/foreach}
    {/block}
{/strip}
</urlset>