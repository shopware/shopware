{extends file="frontend/address/form.tpl"}
{namespace name="frontend/address/index"}

{* Error messages *}
{block name="frontend_address_error_messages"}
    <div class="address-editor--errors is--hidden">
        {include file="frontend/_includes/messages.tpl" type="error" content="{s namespace="frontend/account/internalMessages" name="ErrorFillIn"}{/s}"}
    </div>
{/block}

{block name="frontend_address_action_buttons"}

    <div class="panel--actions address--form-actions is--wide">
        {if $formData.id}
            {block name="frontend_address_action_button_send"}
                <button class="btn is--primary address--form-submit" data-value="update" data-checkFormIsValid="false" data-preloader-button="true">{s name="AddressesActionButtonSend"}{/s}</button>
            {/block}
        {/if}

        {block name="frontend_address_action_button_save_as_new"}
            <button class="btn is--primary address--form-submit" data-value="create" data-checkFormIsValid="false" data-preloader-button="true">{s name="AddressesActionButtonCreate"}{/s}</button>
        {/block}

        {block name="frontend_address_action_button_save_action"}
            <input type="hidden" name="saveAction" value="update" />
        {/block}
    </div>

{/block}

{block name='frontend_address_form_input_set_default_shipping'}{/block}
{block name='frontend_address_form_input_set_default_billing'}{/block}