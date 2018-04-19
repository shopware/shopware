{block name="frontend_listing_box_article_includes"}

    {if $productBoxLayout == 'minimal'}
        {include file="frontend/listing/product-box/box-minimal.tpl"}

    {elseif $productBoxLayout == 'image'}
        {include file="frontend/listing/product-box/box-big-image.tpl"}

    {elseif $productBoxLayout == 'slider'}
        {include file="frontend/listing/product-box/box-product-slider.tpl"}

    {elseif $productBoxLayout == 'emotion'}
        {include file="frontend/listing/product-box/box-emotion.tpl"}
    {elseif $productBoxLayout == 'list'}
        {include file="frontend/listing/product-box/box-list.tpl"}

    {else}
        {block name="frontend_listing_box_article_includes_additional"}
            {include file="frontend/listing/product-box/box-basic.tpl" productBoxLayout="basic"}
        {/block}
    {/if}
{/block}