{block name='frontend_compare_modal'}
    <div class="compare--wrapper">
        <div class="modal--compare">

            {block name='frontend_compare_modal_description'}
                {include file="frontend/compare/col_description.tpl" sArticle=$sComparison.articles sProperties=$sComparison.properties}
            {/block}

            {* Scrolling articles *}
            {block name='frontend_compare_modal_products'}
                {foreach $sComparisonsList.articles as $key => $sComparison}
                    {include file="frontend/compare/col.tpl" sArticle=$sComparison sProperties=$sComparison.properties}
                {/foreach}
            {/block}
        </div>
    </div>
{/block}