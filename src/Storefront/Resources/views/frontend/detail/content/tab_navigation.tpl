{block name="frontend_detail_index_tabs_navigation"}
    <div class="tab--navigation">
        {block name="frontend_detail_index_tabs_navigation_inner"}
            {block name="frontend_detail_index_related_similiar_tabs"}

                {* Tab navigation - Accessory products *}
                {block name="frontend_detail_tabs_entry_related"}
                    {if $sArticle.sRelatedArticles && !$sArticle.crossbundlelook}
                        <a href="#content--related-products" title="{s namespace="frontend/detail/tabs" name='DetailTabsAccessories'}{/s}" class="tab--link">
                            {s namespace="frontend/detail/tabs" name='DetailTabsAccessories'}{/s}
                            <span class="product--rating-count-wrapper">
                                <span class="product--rating-count">{$sArticle.sRelatedArticles|@count}</span>
                            </span>
                        </a>
                    {/if}
                {/block}

                {* Similar products *}
                {block name="frontend_detail_index_recommendation_tabs_entry_similar_products"}
                    {if count($sArticle.sSimilarArticles) > 0}
                        <a href="#content--similar-products" title="{s name="DetailRecommendationSimilarLabel" namespace="frontend/detail/index"}{/s}" class="tab--link">{s name="DetailRecommendationSimilarLabel" namespace="frontend/detail/index"}{/s}</a>
                    {/if}
                {/block}
            {/block}

            {* Customer also bought *}
            {block name="frontend_detail_index_tabs_entry_also_bought"}
                {if $showAlsoBought}
                    <a href="#content--also-bought" title="{s name="DetailRecommendationAlsoBoughtLabel" namespace="frontend/detail/index"}{/s}" class="tab--link">{s name="DetailRecommendationAlsoBoughtLabel" namespace="frontend/detail/index"}{/s}</a>
                {/if}
            {/block}

            {* Customer also viewed *}
            {block name="frontend_detail_index_tabs_entry_also_viewed"}
                {if $showAlsoViewed}
                    <a href="#content--customer-viewed" title="{s name="DetailRecommendationAlsoViewedLabel" namespace="frontend/detail/index"}{/s}" class="tab--link">{s name="DetailRecommendationAlsoViewedLabel" namespace="frontend/detail/index"}{/s}</a>
                {/if}
            {/block}

            {* Related product streams *}
            {block name="frontend_detail_index_tabs_entry_related_product_streams"}
                {foreach $sArticle.relatedProductStreams as $key => $relatedProductStream}
                    <a href="#content--related-product-streams-{$key}" title="{$relatedProductStream.name}" class="tab--link">{$relatedProductStream.name}</a>
                {/foreach}
            {/block}
        {/block}
    </div>
{/block}
