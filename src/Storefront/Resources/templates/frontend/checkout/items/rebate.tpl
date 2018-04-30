{namespace name="frontend/checkout/cart_item"}

<div class="table--tr block-group row--rebate{if $isLast} is--last-row{/if}">

    {* Product information column *}
    {block name='frontend_checkout_cart_item_rebate_name'}
        <div class="table--column column--product block">

            {* Badge *}
            {block name='frontend_checkout_cart_item_rebate_badge'}
                <div class="panel--td column--image">
                    <div class="table--media">
                        <div class="basket--badge">
                            {if $sBasketItem.price >= 0}
                                <i class="icon--arrow-right"></i>
                            {else}
                                <i class="icon--percent2"></i>
                            {/if}
                        </div>
                    </div>
                </div>
            {/block}

            {* Product information *}
            {block name='frontend_checkout_cart_item_rebate_details'}
                <div class="panel--td table--content">

                    {* Product name *}
                    {block name='frontend_checkout_cart_item_rebate_details_title'}
                        <span class="content--title">{$sBasketItem.articlename|strip_tags|truncate:60}</span>
                    {/block}

                    {* Additional product information *}
                    {block name='frontend_checkout_cart_item_rebate_details_inline'}{/block}
                </div>
            {/block}
        </div>
    {/block}

    {* Product tax rate *}
    {block name='frontend_checkout_cart_item_rebate_tax_price'}{/block}

    {* Accumulated product price *}
    {block name='frontend_checkout_cart_item_rebate_total_sum'}
        <div class="panel--td table--column column--total-price block is--align-right">
            {block name='frontend_checkout_cart_item_rebate_total_sum_label'}
                <div class="column--label total-price--label">
                    {s name="CartColumnTotal" namespace="frontend/checkout/cart_header"}{/s}
                </div>
            {/block}

            {block name='frontend_checkout_cart_item_rebate_total_sum_display'}
                {if $sBasketItem.itemInfo}
                    {$sBasketItem.itemInfo}
                {else}
                    {$sBasketItem.price|currency}{block name='frontend_checkout_cart_tax_symbol'}{s name="Star" namespace="frontend/listing/box_article"}{/s}{/block}
                {/if}
            {/block}
        </div>
    {/block}
</div>
