{namespace name="frontend/checkout/cart_item"}

<div class="table--tr block-group row--product{if $isLast} is--last-row{/if}">

    {$calculated = $lineItem.product}

    {$link = {url controller=detail sArticle=$lineItem.id number=$calculated.identifier}}

    {* Product information column *}
    {block name='frontend_checkout_cart_item_name'}
        <div class="column--product">

            {* Product image *}
            {block name='frontend_checkout_cart_item_image'}
                <div class="panel--td column--image">
                    <div class="table--media">
                        {block name="frontend_checkout_cart_item_image_container"}
                            <div class="table--media-outer">
                                {block name="frontend_checkout_cart_item_image_container_outer"}
                                    <div class="table--media-inner">
                                        {block name="frontend_checkout_cart_item_image_container_inner"}

                                            {$name = $lineItem.name|escape|strip_tags}

                                            {if $lineItem.cover.description}
                                                {$coverDescription = $lineItem.cover.description|escape|strip_tags}
                                            {else}
                                                {$coverDescription = $name}
                                            {/if}

                                            <a href="{$link}" title="{$name}" class="table--media-link"
                                                    {if {config name=detailmodal} && {controllerAction|lower} === 'confirm'}
                                                        data-modalbox="true"
                                                        data-content="{url controller="detail" action="productQuickView" ordernumber="{$calculated.identifier}" fullPath forceSecure}"
                                                        data-mode="ajax"
                                                        data-width="750"
                                                        data-sizing="content"
                                                        data-title="{$name|strip_tags|escape}"
                                                        data-updateImages="true"
                                                    {/if}>

                                                {if $lineItem.cover}
                                                    <img srcset="{$lineItem.cover.thumbnails[0].sourceSet}" alt="{$coverDescription}" title="{$coverDescription|truncate:160}" />
                                                {else}
                                                    <img src="{link file='frontend/_public/src/img/no-picture.jpg'}" alt="{$coverDescription}" title="{$coverDescription|truncate:160}" />
                                                {/if}
                                            </a>
                                        {/block}
                                    </div>
                                {/block}
                            </div>
                        {/block}
                    </div>
                </div>
            {/block}

            {* Product information *}
            {block name='frontend_checkout_cart_item_details'}
                <div class="panel--td table--content">

                    {* Product name *}
                    {block name='frontend_checkout_cart_item_details_title'}

                        <a class="content--title" href="{$link}" title="{$name}"
                                {if {config name=detailmodal} && {controllerAction|lower} === 'confirm'}
                            data-modalbox="true"
                            data-content="{url controller="detail" action="productQuickView" ordernumber="{$calculated.identifier}" fullPath forceSecure}"
                            data-mode="ajax"
                            data-width="750"
                            data-sizing="content"
                            data-title="{$name}"
                            data-updateImages="true"
                                {/if}>
                            {$name|truncate:60}
                        </a>
                    {/block}

                    {* Product SKU number *}
                    {block name='frontend_checkout_cart_item_details_sku'}
                        <p class="content--sku content">
                            {s name="CartItemInfoId"}{/s} {$calculated.identifier}
                        </p>
                    {/block}

                    {* Product delivery information *}
                    {block name='frontend_checkout_cart_item_delivery_informations'}
                        {if {config name=BasketShippingInfo} && $sBasketItem.shippinginfo}
                            {include file="frontend/plugins/index/delivery_informations.tpl" sArticle=$sBasketItem}
                        {/if}
                    {/block}

                    {* Additional product information *}
                    {block name='frontend_checkout_cart_item_details_inline'}{/block}
                </div>
            {/block}
        </div>
    {/block}

    {* Product quantity *}
    {block name='frontend_checkout_cart_item_quantity'}
        <div class="panel--td column--quantity is--align-right">

            {* Label *}
            {block name='frontend_checkout_cart_item_quantity_label'}
                <div class="column--label quantity--label">
                    {s name="CartColumnQuantity" namespace="frontend/checkout/cart_header"}{/s}
                </div>
            {/block}

            {block name='frontend_checkout_cart_item_quantity_selection'}
                {if $calculated.quantity}

                    {$start = $lineItem.unit.minPurchase}
                    {$end = $lineItem.unit.maxPurchase}
                    {$step = $lineItem.unit.purchaseStep}

                    {if !$start}
                        {$start = 1}
                    {/if}
                    {if !$end}
                        {$end = 100}
                    {/if}

                    {if !$step}
                        {$step = 1}
                    {/if}

                    <form name="basket_change_quantity{$calculated.identifier}" class="select-field" method="post" action="{url controller='checkout' action='changeQuantity' sTargetAction=$sTargetAction}">
                        <select name="quantity" data-auto-submit="true">
                            {section name="i" start=$start loop=$end step=$step}
                                <option value="{$smarty.section.i.index}" {if $smarty.section.i.index==$calculated.quantity}selected="selected"{/if}>
                                    {$smarty.section.i.index}
                                </option>
                            {/section}
                        </select>
                        <input type="hidden" name="identifier" value="{$calculated.identifier}" />
                    </form>
                {else}
                    {s name="CartColumnQuantityEmpty" namespace="frontend/checkout/cart_item"}{/s}
                {/if}
            {/block}
        </div>
    {/block}

    {* Product unit price *}
    {block name='frontend_checkout_cart_item_price'}
        <div class="panel--td column--unit-price is--align-right">

            {block name='frontend_checkout_cart_item_unit_price_label'}
                <div class="column--label unit-price--label">
                    {s name="CartColumnPrice" namespace="frontend/checkout/cart_header"}{/s}
                </div>
            {/block}

            {$calculated.price.unitPrice|currency}{block name='frontend_checkout_cart_tax_symbol'}{s name="Star" namespace="frontend/listing/box_article"}{/s}{/block}
        </div>
    {/block}

    {* Product tax rate *}
    {block name='frontend_checkout_cart_item_tax_price'}{/block}

    {* Accumulated product price *}
    {block name='frontend_checkout_cart_item_total_sum'}
        <div class="panel--td column--total-price is--align-right">
            {block name='frontend_checkout_cart_item_total_price_label'}
                <div class="column--label total-price--label">
                    {s name="CartColumnTotal" namespace="frontend/checkout/cart_header"}{/s}
                </div>
            {/block}
            {$calculated.price.totalPrice|currency}{block name='frontend_checkout_cart_tax_symbol'}{s name="Star" namespace="frontend/listing/box_article"}{/s}{/block}
        </div>
    {/block}

    {* Remove product from basket *}
    {block name='frontend_checkout_cart_item_delete_article'}
        <div class="panel--td column--actions">
            <form action="{url action='removeLineItem' identifier=$calculated.identifier sTargetAction=$sTargetAction}"
                  method="post">
                <button type="submit" class="btn is--small column--actions-link"
                        title="{"{s name='CartItemLinkDelete'}{/s}"|escape}">
                    <i class="icon--cross"></i>
                </button>
            </form>
        </div>
    {/block}
</div>
