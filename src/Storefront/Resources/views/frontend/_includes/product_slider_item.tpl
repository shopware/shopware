{block name="frontend_common_product_slider_item_config"}
    {$productBoxLayout = ($productBoxLayout) ? $productBoxLayout : ""}
    {$fixedImageSize = ($fixedImageSize) ? $fixedImageSize : ""}
{/block}

{block name="frontend_common_product_slider_item"}
    <div class="product-slider--item">
        {include file="frontend/listing/box_article.tpl" sArticle=$article productBoxLayout=$productBoxLayout fixedImageSize=$fixedImageSize}
    </div>
{/block}