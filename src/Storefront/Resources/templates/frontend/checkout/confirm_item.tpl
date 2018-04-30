{extends file='frontend/checkout/cart_item.tpl'}

{* Tax price for normal products *}
{block name='frontend_checkout_cart_item_tax_price'}
    <div class="panel--td column--tax-price block is--align-right">
        {block name='frontend_checkout_cart_item_tax_label'}
            <div class="column--label tax-price--label">
                {if $sUserData.additional.charge_vat && !$sUserData.additional.show_net}
                    {s name='CheckoutColumnExcludeTax' namespace="frontend/checkout/confirm_header"}{/s}
                {elseif $sUserData.additional.charge_vat}
                    {s name='CheckoutColumnTax' namespace="frontend/checkout/confirm_header"}{/s}
                {/if}
            </div>
        {/block}

        {if $sUserData.additional.charge_vat}{$sBasketItem.tax|currency}{else}&nbsp;{/if}
    </div>
{/block}

{* Additional product relevant information *}
{block name='frontend_checkout_cart_item_details_inline'}
    <div class="product--essential-features">
        {include file="string:{config name=mainfeatures}"}
    </div>
{/block}

{* Voucher tax price *}
{block name='frontend_checkout_cart_item_voucher_tax_price'}
    <div class="panel--td column--tax-price block is--align-right">
        {block name='frontend_checkout_cart_voucher_tax_label'}
            <div class="column--label tax-price--label">
                {if $sUserData.additional.charge_vat && !$sUserData.additional.show_net}
                    {s name='CheckoutColumnExcludeTax' namespace="frontend/checkout/confirm_header"}{/s}
                {elseif $sUserData.additional.charge_vat}
                    {s name='CheckoutColumnTax' namespace="frontend/checkout/confirm_header"}{/s}
                {/if}
            </div>
        {/block}

        {if $sUserData.additional.charge_vat}{$sBasketItem.tax|currency}{else}&nbsp;{/if}
    </div>
{/block}

{* Premium tax price *}
{block name='frontend_checkout_cart_item_premium_tax_price'}
    <div class="panel--td column--tax-price block is--align-right">
        {block name='frontend_checkout_cart_voucher_tax_label'}
            <div class="column--label tax-price--label">
                {if $sUserData.additional.charge_vat && !$sUserData.additional.show_net}
                    {s name='CheckoutColumnExcludeTax' namespace="frontend/checkout/confirm_header"}{/s}
                {elseif $sUserData.additional.charge_vat}
                    {s name='CheckoutColumnTax' namespace="frontend/checkout/confirm_header"}{/s}
                {/if}
            </div>
        {/block}

        {if $sUserData.additional.charge_vat}{$sBasketItem.tax|currency}{else}&nbsp;{/if}
    </div>
{/block}


{* Rebate tax price *}
{block name='frontend_checkout_cart_item_rebate_tax_price'}
    <div class="panel--td column--tax-price block is--align-right">
        {block name='frontend_checkout_cart_voucher_tax_label'}
            <div class="column--label tax-price--label">
                {if $sUserData.additional.charge_vat && !$sUserData.additional.show_net}
                    {s name='CheckoutColumnExcludeTax' namespace="frontend/checkout/confirm_header"}{/s}
                {elseif $sUserData.additional.charge_vat}
                    {s name='CheckoutColumnTax' namespace="frontend/checkout/confirm_header"}{/s}
                {/if}
            </div>
        {/block}

        {if $sUserData.additional.charge_vat}{$sBasketItem.tax|currency}{else}&nbsp;{/if}
    </div>
{/block}

{* Hide tax symbols *}
{block name='frontend_checkout_cart_tax_symbol'}{/block}