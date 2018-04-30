<div class="payment--method-list panel has--border is--rounded block">

    {block name='frontend_checkout_payment_headline'}
        <h3 class="payment--method-headline panel--title is--underline">{s namespace='frontend/checkout/shipping_payment' name='ChangePaymentTitle'}{/s}</h3>
    {/block}

    {block name='frontend_checkout_payment_content'}
        <div class="panel--body is--wide block-group">
            {foreach $paymentMethods as $payment}
                <div class="payment--method block{if $payment@last} method_last{else} method{/if}">

                    {* Radio Button *}
                    {block name='frontend_checkout_payment_fieldset_input_radio'}
                        <div class="method--input">
                            <input type="radio" name="paymentMethodId" class="radio auto_submit" value="{$payment.id}" id="payment{$payment.id}"{if $payment.id eq $currentPaymentId} checked="checked"{/if} />
                        </div>
                    {/block}

                    {* Method Name *}
                    {block name='frontend_checkout_payment_fieldset_input_label'}
                        <div class="method--label is--first">
                            <label class="method--name is--strong" for="payment{$payment.id}">{$payment.label}</label>
                        </div>
                    {/block}

                    {* Method Description *}
                    {block name='frontend_checkout_payment_fieldset_description'}
                        <div class="method--description is--last">
                            {include file="string:{$payment.description}"}
                        </div>
                    {/block}

                    {* Method Logo *}
                    {block name='frontend_checkout_payment_fieldset_template'}
                        <div class="payment--method-logo payment_logo_{$payment.name}"></div>
                    {/block}
                </div>
            {/foreach}
        </div>
    {/block}
</div>