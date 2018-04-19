{block name="frontend_note_item"}
    <div class="note--item panel--tr">

        {if $sBasketItem.sConfigurator}
            {$detailLink={url controller="detail" sArticle=$sBasketItem.articleID number=$sBasketItem.ordernumber}}
        {else}
            {$detailLink=$sBasketItem.linkDetails}
        {/if}

        {* Article information *}
        {block name="frontend_note_item_info"}
            <div class="note--info panel--td">

                {* Article picture *}
                {block name="frontend_note_item_image"}
                    <div class="note--image-container">
                        {$desc = $sBasketItem.articlename|escape}
                        {if $sBasketItem.image.thumbnails[0]}
                            {if $sBasketItem.image.description}
                                {$desc = $sBasketItem.image.description|escape}
                            {/if}
                            <a href="{$detailLink}" title="{$sBasketItem.articlename|escape}" class="note--image-link">
                                <img srcset="{$sBasketItem.image.thumbnails[0].sourceSet}" alt="{$desc}" title="{$desc|truncate:160}" class="note--image" />
                            </a>

                            {* Zoom picture *}
                            {block name="frontend_note_item_image_zoom"}{/block}
                        {else}
                            <a href="{$detailLink}" title="{$sBasketItem.articlename|escape}" class="note--image-link">
                                <img src="{link file='frontend/_public/src/img/no-picture.jpg'}" alt="{$desc}" title="{$desc|truncate:160}" class="note--image" />
                            </a>
                        {/if}
                    </div>
                {/block}

                {* Article details *}
                {block name="frontend_note_item_details"}
                    <div class="note--details">

                        {* Article name *}
                        {block name="frontend_note_item_details_name"}
                            <a class="note--title" href="{$detailLink}" title="{$sBasketItem.articlename|escape}">
                                {$sBasketItem.articlename|truncate:40}
                            </a>
                        {/block}

                        {* Reviews *}
                        {block name="frontend_note_item_rating"}
                            {if !{config name=VoteDisable}}
                                {include file="frontend/_includes/rating.tpl" points=$sBasketItem.sVoteAverage.average type="aggregated"}
                            {/if}
                        {/block}

                        {* Supplier name *}
                        {block name="frontend_note_item_details_supplier"}
                            <div class="note--supplier">
                                {s name="NoteInfoSupplier"}{/s} {$sBasketItem.supplierName}
                            </div>
                        {/block}

                        {* Order number *}
                        {block name="frontend_note_item_details_ordernumber"}
                            <div class="note--ordernumber">
                                {s name="NoteInfoId"}{/s} {$sBasketItem.ordernumber}
                            </div>
                        {/block}

                        {* Date added *}
                        {block name="frontend_note_item_date"}
                            {if $sBasketItem.datum_add}
                                <div class="note--date">
                                    {s name="NoteInfoDate"}{/s} {$sBasketItem.datum_add|date:DATE_MEDIUM}
                                </div>
                            {/if}
                        {/block}

                        {* Delivery information *}
                        {block name="frontend_note_item_delivery"}
                            {if {config name=BASKETSHIPPINGINFO}}
                                <div class="note--delivery{if {config name=VoteDisable}} vote_disabled{/if}"  >
                                    {include file="frontend/plugins/index/delivery_informations.tpl" sArticle=$sBasketItem}
                                </div>
                            {/if}
                        {/block}

                        {block name="frontend_note_index_items"}{/block}
                    </div>
                {/block}
            </div>
        {/block}

        {block name="frontend_note_item_sale"}
            <div class="note--sale panel--td">

                {* Price *}
                {block name="frontend_note_item_price"}
                    {if $sBasketItem.itemInfo}
                        {$sBasketItem.itemInfo}
                    {else}
                        <div class="note--price">{if $sBasketItem.priceStartingFrom}{s namespace='frontend/listing/box_article' name='ListingBoxArticleStartsAt'}{/s} {/if}{$sBasketItem.price|currency}*</div>
                    {/if}
                {/block}

                {* Price unit *}
                {block name="frontend_note_item_unitprice"}
                    {if $sBasketItem.purchaseunit}
                        <span class="note--price-unit">
                            <span class="is--strong">{s name="NoteUnitPriceContent"}{/s}:</span> {$sBasketItem.purchaseunit} {$sBasketItem.sUnit.description}
                            {if $sBasketItem.purchaseunit != $sBasketItem}
                                {if $sBasketItem.referenceunit}
                                    ({$sBasketItem.referenceprice|currency} {s name="Star" namespace="frontend/listing/box_article"}{/s} / {$sBasketItem.referenceunit} {$sBasketItem.sUnit.description})
                                {/if}
                            {/if}
                        </span>
                    {/if}
                {/block}

                {* Compare product *}
                {block name='frontend_note_item_actions_compare'}
                    {if {config name="compareShow"}}
                        <div class="note--compare">
                            <form action="{url controller='compare' action='add_article' articleID=$sBasketItem.articleID}" method="post">
                                <button type="submit"
                                   data-product-compare-add="true"
                                   class="compare--link"
                                   title="{"{s name='ListingBoxLinkCompare'}{/s}"|escape}">
                                    <i class="icon--compare"></i> {s name='ListingBoxLinkCompare'}{/s}
                                </button>
                            </form>
                        </div>
                    {/if}
                {/block}
            </div>
        {/block}

        {* Remove article *}
        {block name="frontend_note_item_delete"}
            <form action="{url controller='note' action='delete' sDelete=$sBasketItem.id}" method="post">
                <button type="submit" title="{"{s name='NoteLinkDelete'}{/s}"|escape}" class="note--delete">
                    <i class="icon--cross"></i>
                </button>
            </form>
        {/block}
    </div>
{/block}

{* Place article in basket *}
{block name="frontend_note_item_actions_buy"}{/block}