{block name='frontend_custom_ajax_buttons_offcanvas'}
    <div class="buttons--off-canvas">
        {block name='frontend_custom_ajax_buttons_offcanvas_inner'}
            <a href="#" title="{"{s name="CustomAjaxActionClose"}{/s}"|escape}" class="close--off-canvas">
                <i class="icon--arrow-left"></i>
                {s name="CustomAjaxActionClose"}{/s}
            </a>
        {/block}
    </div>
{/block}
{block name='frontend_custom_ajax_modal_box'}
    <div class="ajax-modal--custom">
        {block name='frontend_custom_ajax_modal_box_inner'}
            {block name='frontend_custom_ajax_action_buttons'}
                <div class="panel--title is--underline">{$sCustomPage.description}</div>
            {/block}
            {* Article content *}
            {block name='frontend_custom_ajax_article_content'}
                <div class="panel--body is--wide">
                    {$sContent}
                </div>
            {/block}
        {/block}
    </div>
{/block}