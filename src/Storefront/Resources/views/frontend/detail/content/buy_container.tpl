{block name='frontend_detail_index_buy_container'}
    <div class="product--buybox block{if $sArticle.sConfigurator && $sArticle.sConfiguratorSettings.type==2} is--wide{/if}">

        {block name="frontend_detail_rich_snippets_brand"}
            <meta itemprop="brand" content="{$sArticle.supplierName|escape}"/>
        {/block}

        {block name="frontend_detail_rich_snippets_weight"}
            {if $sArticle.weight}
                <meta itemprop="weight" content="{$sArticle.weight} kg"/>
            {/if}
        {/block}

        {block name="frontend_detail_rich_snippets_height"}
            {if $sArticle.height}
                <meta itemprop="height" content="{$sArticle.height} cm"/>
            {/if}
        {/block}

        {block name="frontend_detail_rich_snippets_width"}
            {if $sArticle.width}
                <meta itemprop="width" content="{$sArticle.width} cm"/>
            {/if}
        {/block}

        {block name="frontend_detail_rich_snippets_depth"}
            {if $sArticle.length}
                <meta itemprop="depth" content="{$sArticle.length} cm"/>
            {/if}
        {/block}

        {block name="frontend_detail_rich_snippets_release_date"}
            {if $sArticle.sReleasedate}
                <meta itemprop="releaseDate" content="{$sArticle.sReleasedate}"/>
            {/if}
        {/block}

        {block name='frontend_detail_buy_laststock'}
            {if !$sArticle.isAvailable && ($sArticle.isSelectionSpecified || !$sArticle.sConfigurator)}
                {include file="frontend/_includes/messages.tpl" type="error" content="{s name='DetailBuyInfoNotAvailable' namespace='frontend/detail/buy'}{/s}"}
            {/if}
        {/block}

        {* Product email notification *}
        {block name="frontend_detail_index_notification"}
            {if $sArticle.notification && $sArticle.instock <= 0 && $ShowNotification}
                {include file="frontend/plugins/notification/index.tpl"}
            {/if}
        {/block}

        {* Product data *}
        {block name='frontend_detail_index_buy_container_inner'}
            <div itemprop="offers" itemscope itemtype="{if $sArticle.sBlockPrices}http://schema.org/AggregateOffer{else}http://schema.org/Offer{/if}" class="buybox--inner">

                {block name='frontend_detail_index_data'}
                    {if $sArticle.sBlockPrices}
                        {$lowestPrice=false}
                        {$highestPrice=false}
                        {foreach $sArticle.sBlockPrices as $blockPrice}
                            {if $lowestPrice === false || $blockPrice.price < $lowestPrice}
                                {$lowestPrice=$blockPrice.price}
                            {/if}
                            {if $highestPrice === false || $blockPrice.price > $highestPrice}
                                {$highestPrice=$blockPrice.price}
                            {/if}
                        {/foreach}
                        <meta itemprop="lowPrice" content="{$lowestPrice}"/>
                        <meta itemprop="highPrice" content="{$highestPrice}"/>
                        <meta itemprop="offerCount" content="{$sArticle.sBlockPrices|count}"/>
                    {else}
                        <meta itemprop="priceCurrency" content="{$Shop->getCurrency()->getCurrency()}"/>
                    {/if}
                    {include file="frontend/detail/data.tpl" sArticle=$sArticle sView=1}
                {/block}

                {block name='frontend_detail_index_after_data'}{/block}

                {* Configurator drop down menu's *}
                {block name="frontend_detail_index_configurator"}
                    <div class="product--configurator">
                        {if $sArticle.sConfigurator}
                            {if $sArticle.sConfiguratorSettings.type == 1}
                                {include file="frontend/detail/config_step.tpl"}
                            {elseif $sArticle.sConfiguratorSettings.type == 2}
                                {include file="frontend/detail/config_variant.tpl"}
                            {else}
                                {include file="frontend/detail/config_upprice.tpl"}
                            {/if}
                        {/if}
                    </div>
                {/block}

                {* Include buy button and quantity box *}
                {block name="frontend_detail_index_buybox"}
                    {include file="frontend/detail/buy.tpl"}
                {/block}

                {* Product actions *}
                {block name="frontend_detail_index_actions"}
                    <nav class="product--actions">
                        {include file="frontend/detail/actions.tpl"}
                    </nav>
                {/block}
            </div>
        {/block}

        {* Product - Base information *}
        {block name='frontend_detail_index_buy_container_base_info'}
            <ul class="product--base-info list--unstyled">

                {* Product SKU *}
                {block name='frontend_detail_data_ordernumber'}
                    <li class="base-info--entry entry--sku">

                        {* Product SKU - Label *}
                        {block name='frontend_detail_data_ordernumber_label'}
                            <strong class="entry--label">
                                {s name="DetailDataId" namespace="frontend/detail/data"}{/s}
                            </strong>
                        {/block}

                        {* Product SKU - Content *}
                        {block name='frontend_detail_data_ordernumber_content'}
                            <meta itemprop="productID" content="{$sArticle.articleDetailsID}"/>
                            <span class="entry--content" itemprop="sku">
                                {$sArticle.ordernumber}
                            </span>
                        {/block}
                    </li>
                {/block}

                {* Product attributes fields *}
                {block name='frontend_detail_data_attributes'}

                    {* Product attribute 1 *}
                    {block name='frontend_detail_data_attributes_attr1'}
                        {if $sArticle.attr1}
                            <li class="base-info--entry entry-attribute">
                                <strong class="entry--label">
                                    {s name="DetailAttributeField1Label" namepace="frontend/detail/index"}{/s}:
                                </strong>

                                <span class="entry--content">
                                    {$sArticle.attr1|escape}
                                </span>
                            </li>
                        {/if}
                    {/block}

                    {* Product attribute 2 *}
                    {block name='frontend_detail_data_attributes_attr2'}
                        {if $sArticle.attr2}
                            <li class="base-info--entry entry-attribute">
                                <strong class="entry--label">
                                    {s name="DetailAttributeField2Label" namepace="frontend/detail/index"}{/s}:
                                </strong>

                                <span class="entry--content">
                                    {$sArticle.attr2|escape}
                                </span>
                            </li>
                        {/if}
                    {/block}
                {/block}
            </ul>
        {/block}
    </div>
{/block}
