{$calculated = $lineItem.product}

<div class="modal--checkout-add-article">
    {block name='checkout_ajax_add_title'}
        <div class="modal--title">
            {if !$sBasketInfo}{s name="AjaxAddHeader"}{/s}{else}{s name='AjaxAddHeaderError'}{/s}{/if}
        </div>
    {/block}

    {block name='checkout_ajax_add_error'}
        {if $sBasketInfo}
            <div class="modal--error">
                {include file="frontend/_includes/messages.tpl" type="info" content="{$sBasketInfo}"}
            </div>
        {/if}
    {/block}

    {block name='checkout_ajax_add_information'}
        {$link = {url controller=detail sArticle=$lineItem.id number=$lineItem.number}}

        <div class="modal--article block-group">

            {* Article image *}
            {block name='checkout_ajax_add_information_image'}
                <div class="article--image block">
                    <a href="{$link}" class="link--article-image" title="{$lineItem.name|escape}">

                        {$desc = $lineItem.name|strip_tags|escape}
                        {if $lineItem.cover.description}
                            {$desc = $lineItem.cover.description|strip_tags|escape}
                        {/if}

                        <span class="image--media">
                            {if $lineItem.cover}
                                <img srcset="{$lineItem.cover.thumbnails[0].sourceSet}" alt="{$desc}" title="{$desc|truncate:160}" />
                            {else}
                                {block name='frontend_detail_image_fallback'}
                                    <img src="{link file='frontend/_public/src/img/no-picture.jpg'}" alt="{$desc}" title="{$desc|truncate:160}" />
                                {/block}
                            {/if}
                        </span>
                    </a>
                </div>
            {/block}

            <div class="article--info">
                {* Article Name *}
                {block name='checkout_ajax_add_information_name'}
                    <div class="article--name">
                        <ul class="list--name list--unstyled">
                            <li class="entry--name">
                                <a class="link--name" href="{$link}" title="{$lineItem.name|escape}">
                                    {$lineItem.name|escape|truncate:35}
                                </a>
                            </li>
                            <li class="entry--ordernumber">{s name="AjaxAddLabelOrdernumber"}{/s}: {$lineItem.number}</li>
                        </ul>
                    </div>
                {/block}

                {* Article price *}
                {block name='checkout_ajax_add_information_price'}
                    <div class="article--price">
                        <ul class="list--price list--unstyled">
                            <li class="entry--price">{$calculated.price.unitPrice|currency} {s name="Star" namespace="frontend/listing/box_article"}{/s}</li>
                            <li class="entry--quantity">{s name="AjaxAddLabelQuantity"}{/s}: {$calculated.quantity}</li>
                        </ul>
                    </div>
                {/block}
            </div>
        </div>
    {/block}

    {block name='checkout_ajax_add_actions'}
        <div class="modal--actions">
            {* Contiune shopping *}
            {block name='checkout_ajax_add_actions_continue'}
                <a href="{$link}" data-modal-close="true" title="{s name='AjaxAddLinkBack'}{/s}" class="link--back btn is--secondary is--left is--icon-left is--large">
                    {s name='AjaxAddLinkBack'}{/s} <i class="icon--arrow-left"></i>
                </a>
            {/block}

            {* Forward to the checkout *}
            {block name='checkout_ajax_add_actions_checkout'}
                <a href="{url controller=checkout action=cart}" title="{s name='AjaxAddLinkCart'}{/s}" class="link--confirm btn is--primary right is--icon-right is--large">
                    {s name='AjaxAddLinkCart'}{/s} <i class="icon--arrow-right"></i>
                </a>
            {/block}
        </div>
    {/block}

    {block name='checkout_ajax_add_cross_selling'}
        {if $sCrossSimilarShown|@count || $sCrossBoughtToo|@count}
            <div class="modal--cross-selling">
                <div class="panel has--border is--rounded">

                    {* Cross sellung title *}
                    {block name='checkout_ajax_add_cross_selling_title'}
                        <div class="panel--title is--underline">
                            {s name="AjaxAddHeaderCrossSelling"}{/s}
                        </div>
                    {/block}

                    {* Cross selling panel body *}
                    {block name='checkout_ajax_add_cross_selling_panel'}
                        <div class="panel--body">

                            {* Cross selling product slider *}
                            {block name='checkout_ajax_add_cross_slider'}
                                {if $sCrossBoughtToo|count < 1 && $sCrossSimilarShown}
                                    {$sCrossSellingArticles = $sCrossSimilarShown}
                                {else}
                                    {$sCrossSellingArticles = $sCrossBoughtToo}
                                {/if}

                                {include file="frontend/_includes/product_slider.tpl" articles=$sCrossSellingArticles}
                            {/block}
                        </div>
                    {/block}
                </div>
            </div>
        {/if}
    {/block}
</div>
