{namespace name="frontend/listing/box_article"}

<div class="product--price">

    {* Default price *}
    {block name='frontend_listing_box_article_price_default'}
        <span class="price--default is--nowrap{if $sArticle.has_pseudoprice} is--discount{/if}">
            {if $sArticle.priceStartingFrom && !$sArticle.liveshoppingData}{s name='ListingBoxArticleStartsAt'}{/s} {/if}
            {$sArticle.price|currency}
            {s name="Star"}{/s}
        </span>
    {/block}

    {* Discount price *}
    {block name='frontend_listing_box_article_price_discount'}
        {if $sArticle.has_pseudoprice}
            <span class="price--pseudo">

                {block name='frontend_listing_box_article_price_discount_before'}
                    {s name="priceDiscountLabel" namespace="frontend/detail/data"}{/s}
                {/block}

                <span class="price--discount is--nowrap">
                    {$sArticle.pseudoprice|currency}
                    {s name="Star"}{/s}
                </span>

                {block name='frontend_listing_box_article_price_discount_after'}
                    {s name="priceDiscountInfo" namespace="frontend/detail/data"}{/s}
                {/block}
            </span>
        {/if}
    {/block}
</div>
