{extends file='frontend/listing/product-box/box-basic.tpl'}

{* Product description *}
{block name='frontend_listing_box_article_description'}
    <div class="product--description">
        {$sArticle.description_long|strip_tags|truncate:380}
    </div>
{/block}