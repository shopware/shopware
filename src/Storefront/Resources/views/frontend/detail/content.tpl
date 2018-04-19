{block name='frontend_index_content_inner'}
    <div class="content product--details" itemscope itemtype="http://schema.org/Product"{if !{config name=disableArticleNavigation}} data-product-navigation="{url module="widgets" controller="listing" action="productNavigation"}" data-category-id="{$sArticle.categoryID}" data-main-ordernumber="{$sArticle.mainVariantNumber}"{/if} data-ajax-wishlist="true" data-compare-ajax="true"{if $theme.ajaxVariantSwitch} data-ajax-variants-container="true"{/if}>

        {* The configurator selection is checked at this early point
           to use it in different included files in the detail template. *}
        {block name='frontend_detail_index_configurator_settings'}

            {* Variable for tracking active user variant selection *}
            {$activeConfiguratorSelection = true}

            {if $sArticle.sConfigurator && ($sArticle.sConfiguratorSettings.type == 1 || $sArticle.sConfiguratorSettings.type == 2)}
                {* If user has no selection in this group set it to false *}
                {foreach $sArticle.sConfigurator as $configuratorGroup}
                    {if !$configuratorGroup.selected_value}
                        {$activeConfiguratorSelection = false}
                    {/if}
                {/foreach}
            {/if}
        {/block}

        {* Product header *}
        {include file="frontend/detail/content/header.tpl"}

        <div class="product--detail-upper block-group">
            {* Product image *}
            {block name='frontend_detail_index_image_container'}
                <div class="product--image-container image-slider{if $sArticle.image && {config name=sUSEZOOMPLUS}} product--image-zoom{/if}"
                    {if $sArticle.image}
                    data-image-slider="true"
                    data-image-gallery="true"
                    data-maxZoom="{$theme.lightboxZoomFactor}"
                    data-thumbnails=".image--thumbnails"
                    {/if}>
                    {include file="frontend/detail/image.tpl"}
                </div>
            {/block}

            {* "Buy now" box container *}
            {include file="frontend/detail/content/buy_container.tpl"}
        </div>

        {* Product bundle hook point *}
        {block name="frontend_detail_index_bundle"}{/block}

        {block name="frontend_detail_index_detail"}

            {* Tab navigation *}
            {block name="frontend_detail_index_tabs"}
                {include file="frontend/detail/tabs.tpl"}
            {/block}
        {/block}

        {* Crossselling tab panel *}
        {block name="frontend_detail_index_tabs_cross_selling"}

            {$showAlsoViewed = {config name=similarViewedShow}}
            {$showAlsoBought = {config name=alsoBoughtShow}}
            <div class="tab-menu--cross-selling"{if $sArticle.relatedProductStreams} data-scrollable="true"{/if}>

                {* Tab navigation *}
                {include file="frontend/detail/content/tab_navigation.tpl"}

                {* Tab content container *}
                {include file="frontend/detail/content/tab_container.tpl"}
            </div>
        {/block}
    </div>
{/block}
