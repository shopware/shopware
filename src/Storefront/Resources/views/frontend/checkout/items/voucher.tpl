{namespace name="frontend/checkout/cart_item"}

<div class="table--tr block-group row--voucher{if $isLast} is--last-row{/if}">

    {* Product information column *}
    {block name='frontend_checkout_cart_item_voucher_name'}
        <div class="table--column column--product block">

            {* Badge *}
            {block name='frontend_checkout_cart_item_voucher_badge'}
                <div class="panel--td column--image">
                    <div class="table--media">
                        <div class="basket--badge">
                            <i class="icon--coupon"></i>
                        </div>
                    </div>
                </div>
            {/block}

            {* Product information *}
            {block name='frontend_checkout_cart_item_voucher_details'}
                <div class="panel--td table--content">

                    {* label *}
                    {block name='frontend_checkout_cart_item_voucher_details_title'}
                        <span class="content--title">{$lineItem.code|strip_tags|truncate:60}</span>
                    {/block}

                    {* SKU number *}
                    {block name='frontend_checkout_cart_item_voucher_details_sku'}
                        <p class="content--sku content">
                            {s name="CartItemInfoId"}{/s} {$lineItem.code}
                        </p>
                    {/block}

                    {* Additional product information *}
                    {block name='frontend_checkout_cart_item_voucher_details_inline'}{/block}
                </div>
            {/block}
        </div>
    {/block}

    {* Product tax rate *}
    {block name='frontend_checkout_cart_item_voucher_tax_price'}{/block}

    {* Accumulated product price *}
    {block name='frontend_checkout_cart_item_voucher_total_sum'}
        <div class="panel--td column--total-price block is--align-right">
            {block name='frontend_checkout_cart_item_voucher_total_sum_label'}
                <div class="column--label total-price--label">
                    {s name="CartColumnTotal" namespace="frontend/checkout/cart_header"}{/s}
                </div>
            {/block}

            {block name='frontend_checkout_cart_item_voucher_total_sum_display'}
                {$lineItem.price.unitPrice|currency}{block name='frontend_checkout_cart_tax_symbol'}{s name="Star" namespace="frontend/listing/box_article"}{/s}{/block}
            {/block}
        </div>
    {/block}

    {* Remove voucher from basket *}
    {block name='frontend_checkout_cart_item_voucher_delete_article'}
        <div class="panel--td column--actions block">
            <form action="{url action='removeLineItem' identifier=$lineItem.identifier sTargetAction=$sTargetAction}" method="post">
                <button type="submit" class="btn is--small column--actions-link" title="{"{s name='CartItemLinkDelete'}{/s}"|escape}">
                    <i class="icon--cross"></i>
                </button>
            </form>
        </div>
    {/block}
</div>