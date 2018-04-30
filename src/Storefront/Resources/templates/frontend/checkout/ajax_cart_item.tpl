{namespace name="frontend/checkout/ajax_cart"}

{* Product *}
{if $lineItem.type == "product"}
    {block name='frontend_checkout_cart_item_product'}
        {include file="frontend/checkout/ajax_items/product.tpl"}
    {/block}

{* Voucher *}
{elseif $lineItem.type == "voucher"}
    {block name='frontend_checkout_cart_item_voucher'}
        {include file="frontend/checkout/ajax_items/voucher.tpl"}
    {/block}
{/if}
