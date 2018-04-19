{if $sCompareAddResult|is_bool}
    {include file="frontend/compare/index.tpl"}
{else}
    <div class="compare--wrapper">
        <div class="modal--compare is--fluid" data-max-reached="true">
            {* Compare modal header title *}
            {block name="product_compare_error_title"}
                <div class="modal--title">
                    {s name="CompareHeaderTitle" namespace="frontend/compare/add_article"}{/s}
                </div>
            {/block}

            {* Compare modal error message *}
            {block name="product_compare_error_title"}
                <div class="modal--error">
                    {include file="frontend/_includes/messages.tpl" type="info" content="{s name='CompareInfoMaxReached' namespace='frontend/compare/added'}{/s}"}
                </div>
            {/block}
        </div>
    </div>
{/if}