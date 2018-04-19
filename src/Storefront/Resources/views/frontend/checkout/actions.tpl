<div class="actions">
    {* Continue shopping *}
    {block name="frontend_checkout_actions_link_last"}{/block}

    {if !$sMinimumSurcharge && ($sInquiry || $sDispatchNoOrder)}
        {block name="frontend_checkout_actions_inquiry"}
        <a href="{$sInquiryLink}" title="{"{s name='CheckoutActionsLinkOffer'}{/s}"|escape}" class="button-middle large">
            {s name="CheckoutActionsLinkOffer"}{/s}
        </a>
        {/block}
    {/if}

    {* Checkout *}
    {if !$sMinimumSurcharge && !$sDispatchNoOrder}
        {block name="frontend_checkout_actions_confirm"}
        <a href="{if {config name=always_select_payment}}{url controller='checkout' action='shippingPayment'}{else}{url controller='checkout' action='confirm'}{/if}" title="{"{s name='CheckoutActionsLinkProceed'}{/s}"|escape}" class="button-right large right checkout" >
            {s name="CheckoutActionsLinkProceed"}{/s}
        </a>
        {/block}
    {/if}

    <div class="clear">&nbsp;</div>
</div>
