{extends file='frontend/checkout/cart_item.tpl'}

{* Article price *}
{block name='frontend_checkout_cart_item_price'}{/block}

{* Delivery informations *}
{block name='frontend_checkout_cart_item_delivery_informations'}{/block}

{* Article amount *}
{block name='frontend_checkout_cart_item_quantity'}
    <div class="table--column column--quantity block is--align-right">
        {* Label *}
        {block name='frontend_checkout_cart_item_quantity_label'}
            <div class="column--label quantity--label">
                {s name="CartColumnQuantity" namespace="frontend/checkout/cart_header"}{/s}
            </div>
        {/block}

        {$calculated.quantity}
    </div>
{/block}

{* Remove all the delete buttons for products *}
{block name='frontend_checkout_cart_item_delete_article'}{/block}
{block name='frontend_checkout_cart_item_voucher_delete'}{/block}
{block name='frontend_checkout_cart_item_premium_delete'}{/block}
{block name='frontend_checkout_cart_item_premium_delete_article'}{/block}
{block name='frontend_checkout_cart_item_voucher_delete_article'}{/block}
