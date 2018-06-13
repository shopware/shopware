{extends file="frontend/index/index.tpl"}

{* Hide sidebar left *}
{block name='frontend_index_content_left'}{/block}

{* Hide breadcrumb *}
{block name='frontend_index_breadcrumb'}{/block}

{* Hide shop navigation *}
{block name='frontend_index_shop_navigation'}{/block}

{* Step box *}
{block name='frontend_index_navigation_categories_top'}
    {include file="frontend/register/steps.tpl" sStepActive="register"}
{/block}

{* Hide top bar *}
{block name='frontend_index_top_bar_container'}{/block}

{* Hide footer *}
{block name="frontend_index_footer"}{/block}