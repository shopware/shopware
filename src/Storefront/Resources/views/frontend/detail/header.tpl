{extends file='frontend/index/header.tpl'}

{* Meta title *}
{block name="frontend_index_header_title"}{if $sArticle.metaTitle}{$sArticle.metaTitle|escapeHtml} | {{config name=sShopname}|escapeHtml}{else}{$sArticle.articleName} | {$smarty.block.parent}{/if}{/block}

{* Meta opengraph tags *}
{block name='frontend_index_header_meta_tags_opengraph'}
    <meta property="og:type" content="product" />
    <meta property="og:site_name" content="{{config name=sShopname}|escapeHtml}" />
    <meta property="og:url" content="{url sArticle=$sArticle.articleID title=$sArticle.articleName}" />
    <meta property="og:title" content="{$sArticle.articleName|escapeHtml}" />
    <meta property="og:description" content="{$sArticle.description_long|strip_tags|trim|truncate:240|escapeHtml}" />
    <meta property="og:image" content="{$sArticle.image.source}" />

    <meta property="product:brand" content="{$sArticle.supplierName|escapeHtml}" />
    <meta property="product:price" content="{$sArticle.price}" />
    <meta property="product:product_link" content="{url sArticle=$sArticle.articleID title=$sArticle.articleName}" />

    <meta name="twitter:card" content="product" />
    <meta name="twitter:site" content="{{config name=sShopname}|escapeHtml}" />
    <meta name="twitter:title" content="{$sArticle.articleName|escapeHtml}" />
    <meta name="twitter:description" content="{$sArticle.description_long|strip_tags|trim|truncate:240|escapeHtml}" />
    <meta name="twitter:image" content="{$sArticle.image.source}" />
{/block}

{* Keywords *}
{block name="frontend_index_header_meta_keywords"}{if $sArticle.keywords}{$sArticle.keywords|escapeHtml}{elseif $sArticle.sDescriptionKeywords}{$sArticle.sDescriptionKeywords|escapeHtml}{/if}{/block}

{* Description *}
{block name="frontend_index_header_meta_description"}{if $sArticle.description}{$sArticle.description|escapeHtml}{else}{$sArticle.description_long|strip_tags|trim|escapeHtml}{/if}{/block}

{* Canonical link *}
{block name='frontend_index_header_canonical'}
    <link rel="canonical" href="{url sArticle=$sArticle.articleID title=$sArticle.articleName}" />
{/block}
