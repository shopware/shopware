{extends file="frontend/listing/box_article.tpl"}

{* Description *}
{block name='frontend_listing_box_article_description'}
    <p class="product--description blog--description">
        {$sArticle.description_long|strip_tags|truncate:220}
    </p>
{/block}