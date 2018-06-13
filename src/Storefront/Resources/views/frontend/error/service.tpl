{extends file='frontend/index/index.tpl'}

{block name='frontend_index_header_title' prepend}{s name="ServiceIndexTitle"}{/s} | {/block}

{block name='frontend_index_content'}
    {block name="frontend_error_service_content_outer"}
        <div class="error--service-wrapper">
            {block name="frontend_error_service_content_inner"}
                <h2 class="error--service-header">{s name="ServiceHeader"}{/s}</h2>
                <p class="error--service-text">{s name="ServiceText"}{/s}</p>
            {/block}
        </div>
    {/block}
{/block}

{* Disable left navigation *}
{block name='frontend_index_content_left'}{/block}

{* Disable top bar *}
{block name='frontend_index_top_bar_container'}{/block}

{* Disable search bar / my account button / basket button *}
{block name='frontend_index_shop_navigation'}{/block}