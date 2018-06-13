{* Article compare group *}
{block name="frontend_compare_article_group"}
    <div class="compare--group">

        {* Article compare group unordered list *}
        {block name="frontend_compare_article_group_list"}
            <ul class="compare--group-list list--unstyled">
                {block name="frontend_compare_article_picture"}
                    <li class="list--entry entry--picture">
                        {* Product image - uses the picturefill polyfill for the HTML5 "picture" element *}
                        <a href="{$sArticle.linkDetails}" title="{$sArticle.articleName|escape}" class="box--image">
                            <span class="image--element">
                                <span class="image--media">

                                    {$desc = $sArticle.articleName|escape}

                                    {if isset($sArticle.image.thumbnails)}

                                        {if $sArticle.image.description}
                                            {$desc = $sArticle.image.description|escape}
                                        {/if}

                                        <img srcset="{$sArticle.image.thumbnails[0].sourceSet}"
                                             alt="{$desc}"
                                             title="{$desc|truncate:160}" />
                                    {else}
                                        <img src="{link file='frontend/_public/src/img/no-picture.jpg'}"
                                             alt="{$desc}"
                                             title="{$desc|truncate:160}" />
                                    {/if}
                                </span>
                            </span>
                        </a>
                    </li>
                {/block}

                {block name='frontend_compare_article_name'}
                    <li class="list--entry entry--name">
                        <a class="link--name" href="{$sArticle.linkDetails}" title="{$sArticle.articleName|escape}">{$sArticle.articleName|truncate:47}</a>

                        {block name='frontend_compare_article_name_button'}
                            <a href="{$sArticle.linkDetails}" title="{$sArticle.articleName|escape}" class="btn is--primary is--center is--full is--icon-right btn--product">
                                {s name='ListingBoxLinkDetails' namespace="frontend/listing/box_article"}{/s}
                                <i class="icon--arrow-right"></i>
                            </a>
                        {/block}
                    </li>
                {/block}

                {block name='frontend_compare_votings'}
                    <li class="list--entry entry--voting">
                        {include file="frontend/_includes/rating.tpl" points=$sArticle.sVoteAverage.average label=false}
                    </li>
                {/block}

                {block name='frontend_compare_description'}
                    <li class="list--entry entry--description">
                        {$sArticle.description_long|strip_tags|truncate:100}
                    </li>
                {/block}

                {block name='frontend_compare_price'}
                    <li class="list--entry entry--price">
                        {* Article pseudoprice *}
                        {block name='frontend_compare_price_pseudoprice'}
                            {if $sArticle.has_pseudoprice}
                                <span class="price--pseudo">

                                    {block name='frontend_compare_price_pseudoprice_before'}
                                        {s name="priceDiscountLabel" namespace="frontend/detail/data"}{/s}
                                    {/block}

                                    <span class="price--pseudoprice">
                                        {$sArticle.pseudoprice|currency}
                                        {s name="Star" namespace="frontend/listing/box_article"}{/s}<br />
                                    </span>

                                    {block name='frontend_compare_price_pseudoprice_after'}
                                        {s name="priceDiscountInfo" namespace="frontend/detail/data"}{/s}
                                    {/block}
                                </span>
                            {/if}
                        {/block}

                        {* Article normal or discount price *}
                        {block name='frontend_compare_price_normal'}
                            <span class="price--normal{if $sArticle.has_pseudoprice} price--reduced{/if}">
                                {if $sArticle.priceStartingFrom}
                                    {s name="ComparePriceFrom"}{/s}
                                {/if}

                                {$sArticle.price|currency}
                                {s name="Star" namespace="frontend/listing/box_article"}{/s}
                            </span>
                        {/block}
                    {/block}

                    {* Article unit price *}
                    {block name='frontend_compare_unitprice'}
                        {if $sArticle.purchaseunit}
                            <div class="price--unit">
                                <strong class="price--unit-commpare">{s name="CompareContent"}{/s}:</strong> {$sArticle.purchaseunit} {$sArticle.sUnit.description}
                                {if $sArticle.purchaseunit != $sArticle.referenceunit}
                                    {if $sArticle.referenceunit}
                                        <span class="is--nowrap">
                                            <strong class="price--unit-baseprice">{s name="CompareBaseprice"}{/s}:</strong>
                                        </span>
                                        <span class="is--nowrap">
                                            {$sArticle.referenceunit} {$sArticle.sUnit.description} = {$sArticle.referenceprice|currency} {s name="Star" namespace="frontend/listing/box_article"}{/s}
                                        </span>
                                    {/if}
                                {/if}
                            </div>
                        {/if}
                    </li>
                {/block}

                {* Article properties if exists *}
                {block name='frontend_compare_property_list'}
                    {if $sArticle.sProperties|count}
                        {foreach $sArticle.sProperties as $property}
                            {block name='frontend_compare_properties'}
                                <li class="list--entry entry--property" data-property-row="{$property@iteration}">
                                    {if $property.value}{$property.value}{else}-{/if}
                                </li>
                            {/block}
                        {/foreach}
                    {/if}
                {/block}
            </ul>
        {/block}
    </div>
{/block}
