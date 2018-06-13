{* Product *}
{if $lineItem.type == "product"}
    {block name='frontend_checkout_cart_item_product'}
        {include file="frontend/checkout/items/product.tpl" isLast=$isLast}
    {/block}

{* Voucher *}
{elseif $lineItem.type == "voucher"}
    {block name='frontend_checkout_cart_item_voucher'}
        {include file="frontend/checkout/items/voucher.tpl" isLast=$isLast}
    {/block}

{elseif $lineItem.type == "discount"}
    {block name='frontend_checkout_cart_item_voucher'}
        {include file="frontend/checkout/items/discount.tpl" isLast=$isLast}
    {/block}
{/if}