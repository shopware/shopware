{extends file="frontend/index/header.tpl"}

{block name='frontend_index_header_canonical'}
    <link rel="canonical" href="{url controller=ticket sFid=$sSupport.id}" />
{/block}

{* title *}
{block name="frontend_index_header_title"}{if $sSupport.metaTitle}{$sSupport.metaTitle|escapeHtml} | {{config name=sShopname}|escapeHtml}{else}{$smarty.block.parent}{/if}{/block}

{* Keywords *}
{block name="frontend_index_header_meta_keywords"}{if $sSupport.metaKeywords}{$sSupport.metaKeywords|escapeHtml}{else}{$smarty.block.parent}{/if}{/block}

{* Description *}
{block name="frontend_index_header_meta_description"}{if $sSupport.metaDescription}{$sSupport.metaDescription|escapeHtml}{else}{$smarty.block.parent}{/if}{/block}
