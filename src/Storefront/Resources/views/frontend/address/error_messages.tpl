{namespace name="frontend/address/index"}
<div class="account--error">
    {$message = ''}
    {if $type == 'delete'}
        {$message = "{s name="AddressesDeleteErrorMessage"}{/s}"}
    {/if}

    {include file="frontend/register/error_message.tpl" error_messages=[$message]}
</div>