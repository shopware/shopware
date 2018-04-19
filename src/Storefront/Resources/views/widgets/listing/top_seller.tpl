{block name="widgets_listing_top_seller"}
    {if $sCharts|@count}
        {block name="widgets_listing_top_seller_panel"}
            <div class="topseller panel has--border is--rounded">
                {block name="widgets_listing_top_seller_panel_inner"}

                    {block name="widgets_listing_top_seller_title"}
                        <div class="topseller--title panel--title is--underline">
                            {s name="TopsellerHeading" namespace=frontend/plugins/index/topseller}{/s}
                        </div>
                    {/block}

                    {block name="widgets_listing_top_seller_slider"}
                        {include file="frontend/_includes/product_slider.tpl" articles=$sCharts productSliderCls="topseller--content panel--body"}
                    {/block}
                {/block}
            </div>
        {/block}
    {/if}
{/block}