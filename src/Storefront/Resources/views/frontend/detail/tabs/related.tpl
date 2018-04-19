{namespace name="frontend/detail/related"}

{if $sArticle.sRelatedArticles && !$sArticle.crossbundlelook}
    {* Related products - Content *}
    {block name="frontend_detail_index_similar_slider_content"}
        <div class="related--content">
            {include file="frontend/_includes/product_slider.tpl" articles=$sArticle.sRelatedArticles sliderInitOnEvent="onShowContent-related"}
        </div>
    {/block}
{/if}