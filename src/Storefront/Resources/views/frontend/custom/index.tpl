{extends file="frontend/index/index.tpl"}

{* Breadcrumb *}
{block name="frontend_index_start" append}
{$sBreadcrumb = []}
{if $sCustomPage.parent}
    {$sBreadcrumb[] = [
        'name' => {$sCustomPage.parent.page_title|default:$sCustomPage.parent.description},
        'link'=>{url sCustom=$sCustomPage.parent.id}
    ]}
{/if}
{$sBreadcrumb[] = [
    'name' => {$sCustomPage.page_title|default:$sCustomPage.description},
    'link'=>{url sCustom=$sCustomPage.id}
]}
{/block}

{block name="frontend_index_header"}
    {include file="frontend/custom/header.tpl"}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div class="custom-page--content content block">

        {* Custom page tab content *}
        {block name="frontend_custom_article"}
            <div class="content--custom">
                {block name="frontend_custom_article_inner"}
                    {* Custom page tab headline *}
                    {block name="frontend_custom_article_headline"}
                        <h1 class="custom-page--tab-headline">{$sCustomPage.description}</h1>
                    {/block}

                    {* Custom page tab inner content *}
                    {block name="frontend_custom_article_content"}
                        {$sContent}
                    {/block}
                {/block}
            </div>
        {/block}

    </div>
{/block}

{* Sidebar left *}
{block name="frontend_index_content_left"}
    {include file="frontend/index/sidebar.tpl"}
{/block}