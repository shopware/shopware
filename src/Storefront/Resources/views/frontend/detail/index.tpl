{extends file='frontend/index/index.tpl'}

{* Custom header *}
{block name='frontend_index_header'}
    {include file="frontend/detail/header.tpl"}
{/block}

{* Modify the breadcrumb *}
{block name='frontend_index_breadcrumb_inner' prepend}
    {block name="frontend_detail_breadcrumb_overview"}
        {if !{config name=disableArticleNavigation}}
            {$breadCrumbBackLink = $sBreadcrumb[count($sBreadcrumb) - 1]['link']}
            <a class="breadcrumb--button breadcrumb--link" href="{if $breadCrumbBackLink}{$breadCrumbBackLink}{else}#{/if}" title="{s name="DetailNavIndex" namespace="frontend/detail/navigation"}{/s}">
                <i class="icon--arrow-left"></i>
                <span class="breadcrumb--title">{s name='DetailNavIndex' namespace="frontend/detail/navigation"}{/s}</span>
            </a>
        {/if}
    {/block}
{/block}

{block name="frontend_index_content_top" append}
    {* Product navigation - Previous and next arrow button *}
    {block name="frontend_detail_index_navigation"}
        {if !{config name=disableArticleNavigation}}
            <nav class="product--navigation">
                {include file="frontend/detail/navigation.tpl"}
            </nav>
        {/if}
    {/block}
{/block}

{* Main content *}
{block name='frontend_index_content'}
    {include file="frontend/detail/content.tpl"}
{/block}
