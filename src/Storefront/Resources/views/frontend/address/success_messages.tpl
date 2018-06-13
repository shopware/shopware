{namespace name="frontend/address/index"}
<div class="account--success">
    {$content = ''}
    {if $type == 'create'}
        {$content = "{s name='AddressesCreateSuccess'}{/s}"}
    {elseif $type == 'default_billing'}
        {$content = "{s name='AddressesSetDefaultBillingSuccess'}{/s}"}
    {elseif $type == 'default_shipping'}
        {$content = "{s name='AddressesSetDefaultShippingSuccess'}{/s}"}
    {elseif $type == 'update'}
        {$content = "{s name='AddressesUpdateSuccess'}{/s}"}
    {elseif $type == 'delete'}
        {$content = "{s name='AddressesDeleteSuccess'}{/s}"}
    {/if}

    {include file="frontend/_includes/messages.tpl" type="success" content="{$content}"}
</div>