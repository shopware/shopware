{block name="frontend_detail_index_also_bought"}
    {if $boughtArticles}
        {block name="frontend_detail_index_also_bought_slider"}
            <div class="bought--content panel--body">
                {include file="frontend/_includes/product_slider.tpl" articles=$boughtArticles sliderInitOnEvent="onShowContent-alsobought"}
            </div>
        {/block}
    {/if}
{/block}
