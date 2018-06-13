{block name="frontend_detail_index_similar_viewed"}
    {if $viewedArticles}
        {block name="frontend_detail_index_similar_viewed_slider"}
            <div class="viewed--content">
                {include file="frontend/_includes/product_slider.tpl" articles=$viewedArticles sliderInitOnEvent="onShowContent-alsoviewed"}
            </div>
        {/block}
    {/if}
{/block}
