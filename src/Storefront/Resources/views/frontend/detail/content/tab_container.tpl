{block name="frontend_detail_index_outer_tabs"}
    <div class="tab--container-list">
        {block name="frontend_detail_index_inner_tabs"}
            {block name='frontend_detail_index_before_tabs'}{/block}

            {* Accessory articles *}
            {block name="frontend_detail_index_tabs_related"}
                {if $sArticle.sRelatedArticles && !$sArticle.crossbundlelook}
                    <div class="tab--container" data-tab-id="related">
                        {block name="frontend_detail_index_tabs_related_inner"}
                            <div class="tab--header">
                                <a href="#" class="tab--title" title="{s namespace="frontend/detail/tabs" name='DetailTabsAccessories'}{/s}">
                                    {s namespace="frontend/detail/tabs" name='DetailTabsAccessories'}{/s}
                                    <span class="product--rating-count-wrapper">
                                        <span class="product--rating-count">{$sArticle.sRelatedArticles|@count}</span>
                                    </span>
                                </a>
                            </div>
                            <div class="tab--content content--related">{include file="frontend/detail/tabs/related.tpl"}</div>
                        {/block}
                    </div>
                {/if}
            {/block}

            {* Similar products slider *}
            {if $sArticle.sSimilarArticles}
                {block name="frontend_detail_index_tabs_similar"}
                    <div class="tab--container" data-tab-id="similar">
                        {block name="frontend_detail_index_tabs_similar_inner"}
                            <div class="tab--header">
                                <a href="#" class="tab--title" title="{s name="DetailRecommendationSimilarLabel" namespace="frontend/detail/index"}{/s}">{s name="DetailRecommendationSimilarLabel" namespace="frontend/detail/index"}{/s}</a>
                            </div>
                            <div class="tab--content content--similar">{include file='frontend/detail/tabs/similar.tpl'}</div>
                        {/block}
                    </div>
                {/block}
            {/if}

            {* "Customers bought also" slider *}
            {if $showAlsoBought}
                {block name="frontend_detail_index_tabs_also_bought"}
                    <div class="tab--container" data-tab-id="alsobought">
                        {block name="frontend_detail_index_tabs_also_bought_inner"}
                            <div class="tab--header">
                                <a href="#" class="tab--title" title="{s name='DetailRecommendationAlsoBoughtLabel' namespace="frontend/detail/index"}{/s}">{s name='DetailRecommendationAlsoBoughtLabel' namespace="frontend/detail/index"}{/s}</a>
                            </div>
                            <div class="tab--content content--also-bought">{action module=widgets controller=recommendation action=bought articleId=$sArticle.articleID}</div>
                        {/block}
                    </div>
                {/block}
            {/if}

            {* "Customers similar viewed" slider *}
            {if $showAlsoViewed}
                {block name="frontend_detail_index_tabs_also_viewed"}
                    <div class="tab--container" data-tab-id="alsoviewed">
                        {block name="frontend_detail_index_tabs_also_viewed_inner"}
                            <div class="tab--header">
                                <a href="#" class="tab--title" title="{s name='DetailRecommendationAlsoViewedLabel' namespace="frontend/detail/index"}{/s}">{s name='DetailRecommendationAlsoViewedLabel' namespace="frontend/detail/index"}{/s}</a>
                            </div>
                            <div class="tab--content content--also-viewed">{action module=widgets controller=recommendation action=viewed articleId=$sArticle.articleID}</div>
                        {/block}
                    </div>
                {/block}
            {/if}

            {* Related product streams *}
            {foreach $sArticle.relatedProductStreams as $key => $relatedProductStream}
                {block name="frontend_detail_index_tabs_related_product_streams"}
                    <div class="tab--container" data-tab-id="productStreamSliderId-{$relatedProductStream.id}">
                        {block name="frontend_detail_index_tabs_related_product_streams_inner"}
                            <div class="tab--header">
                                <a href="#" class="tab--title" title="{$relatedProductStream.name}">{$relatedProductStream.name}</a>
                            </div>
                            <div class="tab--content content--related-product-streams-{$key}">
                                {include file='frontend/detail/tabs/product_streams.tpl'}
                            </div>
                        {/block}
                    </div>
                {/block}
            {/foreach}

            {block name='frontend_detail_index_after_tabs'}{/block}
        {/block}
    </div>
{/block}
