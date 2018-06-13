{extends file='frontend/checkout/cart_header.tpl'}

{* Hide the price by unit *}
{block name='frontend_checkout_cart_header_price'}{/block}

{* Product tax *}
{block name='frontend_checkout_cart_header_tax'}
    <div class="panel--th column--tax-price block is--align-right">
        {if $sUserData.additional.charge_vat && !$sUserData.additional.show_net}
            {s name='CheckoutColumnExcludeTax'}{/s}
        {elseif $sUserData.additional.charge_vat}
            {s name='CheckoutColumnTax'}{/s}
        {else}&nbsp;{/if}
    </div>
{/block}