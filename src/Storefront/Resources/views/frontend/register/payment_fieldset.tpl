{block name="frontend_register_payment"}
    <div class="panel register--payment">

        {block name="frontend_register_payment_headline"}
            <h2 class="panel--title is--underline">{s name='RegisterPaymentHeadline'}{/s}</h2>
        {/block}

        {block name="frontend_register_payment_fieldset"}
            <div class="panel--body is--wide">
                {foreach $payment_means as $payment_mean}

                    {block name="frontend_register_payment_method"}
                        <div class="payment--method panel--tr">

                            {block name="frontend_register_payment_fieldset_input"}
                                <div class="payment--selection-input">
                                    {block name="frontend_register_payment_fieldset_input_radio"}
                                        <input type="radio" name="register[payment]" value="{$payment_mean.id}" id="payment_mean{$payment_mean.id}"{if $payment_mean.id eq $form_data.payment or (!$form_data && !$payment_mean@index)} checked="checked"{/if} />
                                    {/block}
                                </div>
                                <div class="payment--selection-label">
                                    {block name="frontend_register_payment_fieldset_input_label"}
                                        <label for="payment_mean{$payment_mean.id}" class="is--strong">
                                            {$payment_mean.description}
                                        </label>
                                    {/block}
                                </div>
                            {/block}

                            {block name="frontend_register_payment_fieldset_description"}
                                <div class="payment--description panel--td">
                                    {include file="string:{$payment_mean.additionaldescription}"}
                                </div>
                            {/block}

                            {block name='frontend_register_payment_fieldset_template'}
                                <div class="payment_logo_{$payment_mean.name}"></div>
                                {if "frontend/plugins/payment/`$payment_mean.template`"|template_exists}
                                    <div class="payment--content{if $payment_mean.id != $form_data.payment} is--hidden{/if}">
                                        {include file="frontend/plugins/payment/`$payment_mean.template`" checked = ($payment_mean.id == $form_data.payment)}
                                    </div>
                                {/if}
                            {/block}
                        </div>
                    {/block}

                {/foreach}
            </div>
        {/block}

    </div>
{/block}