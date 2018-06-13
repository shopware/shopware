<div class="dispatch--method-list panel has--border is--rounded block">

    {block name='frontend_checkout_shipping_headline'}
        <h3 class="dispatch--method-headline panel--title is--underline">{s namespace='frontend/checkout/shipping_payment' name='ChangeShippingTitle'}{/s}</h3>
    {/block}

    {block name='frontend_checkout_shipping_content'}
        <div class="panel--body is--wide block-group">
            {foreach $shippingMethods as $method}
                {block name="frontend_checkout_dispatch_container"}
                    <div class="dispatch--method{if $method@last} method_last{else} method{/if} block">

                        {* Radio Button *}
                        {block name='frontend_checkout_dispatch_shipping_input_radio'}
                            <div class="method--input">
                                <input type="radio" id="confirm_dispatch{$method.id}" class="radio auto_submit" value="{$method.id}" name="shippingMethodId"{if $method.id eq $currentShippingMethodId} checked="checked"{/if} />
                            </div>
                        {/block}

                        {* Method Name *}
                        {block name='frontend_checkout_dispatch_shipping_input_label'}
                            <div class="method--label is--first">
                                <label class="method--name is--strong" for="confirm_dispatch{$method.id}">{$method.name}</label>
                            </div>
                        {/block}

                        {* Method Description *}
                        {block name='frontend_checkout_shipping_fieldset_description'}
                            {if $method.description}
                                <div class="method--description">
                                    {$method.description}
                                </div>
                            {/if}
                        {/block}
                    </div>
                {/block}
            {/foreach}

            {* Actions *}
            {block name="frontend_checkout_shipping_action_buttons"}
                <input type="hidden" class="agb-checkbox" name="sAGB" value="{if $sAGBChecked}1{else}0{/if}" />
            {/block}
        </div>
    {/block}
</div>