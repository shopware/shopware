{block name='frontend_register_error_messages'}
    {if $error_messages}
        {if $error_messages|count < 2}
            {include file="frontend/_includes/messages.tpl" type="error" content=$error_messages|array_shift}
        {else}
            {include file="frontend/_includes/messages.tpl" type="error" list=$error_messages}
        {/if}
    {/if}
{/block}