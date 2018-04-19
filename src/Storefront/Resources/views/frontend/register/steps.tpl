{* Step box *}
<div class="steps--container container">
    <div class="steps--content panel--body center">
        {block name='frontend_register_steps'}
            <ul class="steps--list">

                {* First Step - Address *}
                {block name='frontend_register_steps_basket'}
                    <li class="steps--entry step--basket{if $sStepActive=='address'} is--active{/if}">
                        <span class="icon">{s name="CheckoutStepAddressNumber"}{/s}</span>
                        <span class="text"><span class="text--inner">{s name="CheckoutStepAddressText"}{/s}</span></span>
                    </li>
                {/block}

                {* Spacer *}
                {block name='frontend_register_steps_spacer1'}
                    <li class="steps--entry steps--spacer">
                        <i class="icon--arrow-right"></i>
                    </li>
                {/block}

                {* Second Step - Payment *}
                {block name='frontend_register_steps_register'}
                    <li class="steps--entry step--register{if $sStepActive=='paymentShipping'} is--active{/if}">
                        <span class="icon">{s name="CheckoutStepPaymentShippingNumber"}{/s}</span>
                        <span class="text"><span class="text--inner">{s name="CheckoutStepPaymentShippingText"}{/s}</span></span>
                    </li>
                {/block}

                {* Spacer *}
                {block name='frontend_register_steps_spacer2'}
                    <li class="steps--entry steps--spacer">
                        <i class="icon--arrow-right"></i>
                    </li>
                {/block}

                {* Third Step - Confirmation *}
                {block name='frontend_register_steps_confirm'}
                    <li class="steps--entry step--confirm{if $sStepActive=='finished'} is--active{/if}">
                        <span class="icon">{s name="CheckoutStepConfirmNumber"}{/s}</span>
                        <span class="text"><span class="text--inner">{s name="CheckoutStepConfirmText"}{/s}</span></span>
                    </li>
                {/block}
            </ul>
        {/block}
    </div>
</div>