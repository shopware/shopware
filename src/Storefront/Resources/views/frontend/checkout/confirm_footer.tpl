{extends file='frontend/checkout/cart_footer.tpl'}

{block name='frontend_checkout_cart_footer_field_labels_taxes' append}
    {if {config name=countrynotice} && $sCountry.notice && {include file="string:{$sCountry.notice}"} !== ""}
        <li class="list--entry table-footer--country-notice">
        {* Include country specific notice message *}
            <p>{include file="string:{$sCountry.notice}"}</p>
        </li>
    {/if}

    {if !$sUserData.additional.charge_vat && {config name=nettonotice}}
        <li class="list--entry table-footer--netto-notice">
            {include file="frontend/_includes/messages.tpl" type="warning" content="* {s name='CheckoutFinishTaxInformation'}{/s}"}
        </li>
    {/if}
{/block}

{block name='frontend_checkout_cart_footer_add_product'}{/block}

{block name='frontend_checkout_cart_footer_add_voucher'}{/block}