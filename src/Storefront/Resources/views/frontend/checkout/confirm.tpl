{extends file="frontend/index/index.tpl"}

{* Back to the shop button *}
{block name='frontend_index_logo_trusted_shops' append}
    {if $theme.checkoutHeader}
        <a href="{url controller='index'}"
           class="btn is--small btn--back-top-shop is--icon-left"
           title="{"{s name='FinishButtonBackToShop' namespace='frontend/checkout/finish'}{/s}"|escape}"
           xmlns="http://www.w3.org/1999/html">
            <i class="icon--arrow-left"></i>
            {s name="FinishButtonBackToShop" namespace="frontend/checkout/finish"}{/s}
        </a>
    {/if}
{/block}

{* Hide sidebar left *}
{block name='frontend_index_content_left'}
    {if !$theme.checkoutHeader}
        {$smarty.block.parent}
    {/if}
{/block}

{* Hide breadcrumb *}
{block name='frontend_index_breadcrumb'}{/block}

{* Hide shop navigation *}
{block name='frontend_index_shop_navigation'}
    {if !$theme.checkoutHeader}
        {$smarty.block.parent}
    {/if}
{/block}

{* Step box *}
{block name='frontend_index_navigation_categories_top'}
    {if !$theme.checkoutHeader}
        {$smarty.block.parent}
    {/if}
    {include file="frontend/register/steps.tpl" sStepActive="finished"}
{/block}

{* Hide top bar *}
{block name='frontend_index_top_bar_container'}
    {if !$theme.checkoutHeader}
        {$smarty.block.parent}
    {/if}
{/block}

{* Footer *}
{block name="frontend_index_footer"}
    {if !$theme.checkoutFooter}
        {$smarty.block.parent}
    {else}
        {block name='frontend_index_checkout_confirm_footer'}
            {include file="frontend/index/footer_minimal.tpl"}
        {/block}
    {/if}
{/block}

{* Main content *}
{block name='frontend_index_content'}
    <div class="content confirm--content">

    {* Error messages *}
    {block name='frontend_checkout_confirm_error_messages'}
        {include file="frontend/checkout/error_messages.tpl"}
    {/block}

    {block name='frontend_checkout_confirm_form'}
        <form id="confirm--form" method="post" action="{if $sPayment.embediframe || $sPayment.action}{url action='payment'}{else}{url action='finish'}{/if}">

            {* AGB and Revocation *}
            {block name='frontend_checkout_confirm_tos_panel'}
                <div class="tos--panel panel has--border">

                    {block name='frontend_checkout_confirm_tos_panel_headline'}
                        <div class="panel--title primary is--underline">
                            {s name="ConfirmHeadlineAGBandRevocation"}{/s}
                        </div>
                    {/block}

                    <div class="panel--body is--wide">

                        {* Right of revocation notice *}
                        {block name='frontend_checkout_confirm_tos_revocation_notice'}
                            {if {config name=revocationnotice}}
                                <div class="body--revocation" data-modalbox="true" data-targetSelector="a" data-mode="ajax" data-height="500" data-width="750">
                                    {s name="ConfirmTextRightOfRevocationNew"}<p>Bitte beachten Sie bei Ihrer Bestellung auch unsere <a href="{url controller=custom sCustom=8}" data-modal-height="500" data-modal-width="800">Widerrufsbelehrung</a>.</p>{/s}
                                </div>
                            {/if}
                        {/block}

                        {* Hidden field for the user comment *}
                        <textarea class="is--hidden user-comment--hidden" rows="1" cols="1" name="sComment">{$sComment|escape}</textarea>

                        <ul class="list--checkbox list--unstyled">

                            {* Terms of service *}
                            {block name='frontend_checkout_confirm_agb'}
                                <li class="block-group row--tos">
                                    {* Terms of service checkbox *}
                                    {block name='frontend_checkout_confirm_agb_checkbox'}
                                        <span class="block column--checkbox">
                                            {if !{config name='IgnoreAGB'}}
                                                <input type="checkbox" required="required" aria-required="true" id="sAGB" name="sAGB"{if $sAGBChecked} checked="checked"{/if} />
                                            {/if}
                                        </span>
                                    {/block}

                                    {* AGB label *}
                                    {block name='frontend_checkout_confirm_agb_label'}
                                        <span class="block column--label">
                                            <label for="sAGB"{if $sAGBError} class="has--error"{/if} data-modalbox="true" data-targetSelector="a" data-mode="ajax" data-height="500" data-width="750">{s name="ConfirmTerms"}{/s}</label>
                                        </span>
                                    {/block}
                                </li>
                            {/block}

                            {* Service articles and ESD articles *}
                            {block name='frontend_checkout_confirm_service_esd'}

                                {* Service articles *}
                                {block name='frontend_checkout_confirm_service'}
                                    {if $hasServiceArticles}
                                        <li class="block-group row--tos">

                                            {* Service articles checkbox *}
                                            {block name='frontend_checkout_confirm_service_checkbox'}
                                                <span class="block column--checkbox">
                                                    <input type="checkbox" required="required" aria-required="true" name="serviceAgreementChecked" id="serviceAgreementChecked"{if $serviceAgreementChecked} checked="checked"{/if} />
                                                </span>
                                            {/block}

                                            {* Service articles label *}
                                            {block name='frontend_checkout_confirm_service_label'}
                                                <span class="block column--label">
                                                    <label for="swagCRDServiceBox"{if $agreementErrors && $agreementErrors.serviceError} class="has--error"{/if}>
                                                        {s name="AcceptServiceMessage"}{/s}
                                                    </label>
                                                </span>
                                            {/block}
                                        </li>
                                    {/if}
                                {/block}

                                {* ESD articles *}
                                {block name='frontend_checkout_confirm_esd'}
                                    {if $hasEsdArticles}
                                        <li class="block-group row--tos">

                                            {* ESD articles checkbox *}
                                            {block name='frontend_checkout_confirm_esd_checkbox'}
                                                <span class="block column--checkbox">
                                                    <input type="checkbox" required="required" aria-required="true" name="esdAgreementChecked" id="esdAgreementChecked"{if $esdAgreementChecked} checked="checked"{/if} />
                                                </span>
                                            {/block}

                                            {* ESD articles label *}
                                            {block name='frontend_checkout_confirm_esd_label'}
                                                <span class="block column--label">
                                                    <label for="esdAgreementChecked"{if $agreementErrors && $agreementErrors.esdError} class="has--error"{/if}>
                                                        {s name="AcceptEsdMessage"}{/s}
                                                    </label>
                                                </span>
                                            {/block}
                                        </li>
                                    {/if}
                                {/block}

                            {/block}

                            {* Newsletter sign up checkbox *}
                            {block name='frontend_checkout_confirm_newsletter'}
                                {if !$sUserData.additional.user.newsletter && {config name=newsletter}}
                                    <li class="block-group row--newsletter">

                                        {* Newsletter checkbox *}
                                        {block name='frontend_checkout_confirm_newsletter_checkbox'}
                                            <span class="block column--checkbox">
                                                <input type="checkbox" name="sNewsletter" id="sNewsletter" value="1"{if $sNewsletter} checked="checked"{/if} />
                                            </span>
                                        {/block}

                                        {* Newsletter label *}
                                        {block name='frontend_checkout_confirm_newsletter_label'}
                                            <span class="block column--label">
                                                <label for="sNewsletter">
                                                    {s name="ConfirmLabelNewsletter"}{/s}
                                                </label>
                                            </span>
                                        {/block}
                                    </li>
                                {/if}
                            {/block}
                        </ul>

                        {* Additional custom text field which can be used to display the terms of services *}
                        {block name="frontend_checkout_confirm_additional_free_text_display"}
                            {if {config name=additionalfreetext}}
                                <div class="notice--agb">
                                    {s name="ConfirmTextOrderDefault"}{/s}
                                </div>
                            {/if}
                        {/block}

                        {* Additional notice - bank connection *}
                        {block name="frontend_checkout_confirm_bank_connection_notice"}
                            {if {config name=bankConnection}}
                                <p class="notice--change-now">
                                    {s name="ConfirmInfoChange"}{/s}
                                </p>

                                <p class="notice--payment-data">
                                    {s name="ConfirmInfoPaymentData"}{/s}
                                </p>
                            {/if}
                        {/block}
                    </div>
                </div>
            {/block}

            {block name='frontend_checkout_confirm_information_wrapper'}
                <div class="panel--group block-group information--panel-wrapper" data-panel-auto-resizer="true">

                    {block name='frontend_checkout_confirm_information_addresses'}

                        {$billing = $context.customer.activeBillingAddress}
                        {$shipping = $context.customer.activeShippingAddress}
                        {$customer = $context.customer}

                        {if $billing.id == $shipping.id}

                            {* Equal Billing & Shipping *}
                            {block name='frontend_checkout_confirm_information_addresses_equal'}
                                <div class="information--panel-item information--panel-address">

                                    {block name='frontend_checkout_confirm_information_addresses_equal_panel'}
                                        <div class="panel has--border is--rounded block information--panel">

                                            {block name='frontend_checkout_confirm_information_addresses_equal_panel_title'}
                                                <div class="panel--title is--underline">
                                                    {s name='ConfirmAddressEqualTitle'}{/s}
                                                </div>
                                            {/block}

                                            {block name='frontend_checkout_confirm_information_addresses_equal_panel_body'}
                                                <div class="panel--body is--wide">

                                                    {block name='frontend_checkout_confirm_information_addresses_equal_panel_billing'}
                                                        <div class="billing--panel">
                                                            {if $billing.company}
                                                                <span class="address--company is--bold">{$billing.company|escapeHtml}</span>{if $billing.department|escapeHtml}<br /><span class="address--department is--bold">{$billing.department|escapeHtml}</span>{/if}
                                                                <br />
                                                            {/if}

                                                            <span class="address--salutation">{$billing.salutation|salutation}</span>
                                                            {if {config name="displayprofiletitle"}}
                                                                <span class="address--title">{$billing.title|escapeHtml}</span><br/>
                                                            {/if}
                                                            <span class="address--firstname">{$billing.firstname|escapeHtml}</span> <span class="address--lastname">{$billing.lastname|escapeHtml}</span><br/>
                                                            <span class="address--street">{$billing.street|escapeHtml}</span><br />
                                                            {if $billing.additionalAddressLine1}<span class="address--additional-one">{$billing.additionalAddressLine1|escapeHtml}</span><br />{/if}
                                                            {if $billing.additionalAddressLine2}<span class="address--additional-two">{$billing.additionalAddressLine2|escapeHtml}</span><br />{/if}
                                                            {if {config name=showZipBeforeCity}}
                                                                <span class="address--zipcode">{$billing.zipcode|escapeHtml}</span> <span class="address--city">{$billing.city|escapeHtml}</span>
                                                            {else}
                                                                <span class="address--city">{$billing.city|escapeHtml}</span> <span class="address--zipcode">{$billing.zipcode|escapeHtml}</span>
                                                            {/if}<br />
                                                            {if $billing.state.name}<span class="address--statename">{$billing.state.name|escapeHtml}</span><br />{/if}
                                                            <span class="address--countryname">{$billing.country.name|escapeHtml}</span>

                                                            {block name="frontend_checkout_confirm_information_addresses_equal_panel_billing_invalid_data"}
                                                                {if $invalidBillingAddress}
                                                                    {include file='frontend/_includes/messages.tpl' type="warning" content="{s name='ConfirmAddressInvalidAddress'}{/s}"}
                                                                {else}
                                                                    {block name="frontend_checkout_confirm_information_addresses_equal_panel_billing_set_as_default"}
                                                                        {if $billing.id != $customer.defaultBillingAddress.id || $shipping.id != $customer.defaultShippingAddress.id}
                                                                            <div class="set-default">
                                                                                <input type="checkbox" name="setAsDefaultAddress" id="set_as_default" value="1" /> <label for="set_as_default">{s name="ConfirmUseForFutureOrders"}{/s}</label>
                                                                            </div>
                                                                        {/if}
                                                                    {/block}
                                                                {/if}
                                                            {/block}
                                                        </div>
                                                    {/block}

                                                    {block name='frontend_checkout_confirm_information_addresses_equal_panel_shipping'}
                                                        <div class="shipping--panel">
                                                            {block name='frontend_checkout_confirm_information_addresses_equal_panel_shipping_select_address'}
                                                                <a href="{url controller=address}"
                                                                   class="btn choose-different-address"
                                                                   data-address-selection="true"
                                                                   data-sessionKey="checkoutShippingAddressId"
                                                                   data-id="{$shipping.id}"
                                                                   title="{s name="ConfirmAddressChooseDifferentShippingAddress"}{/s}">
                                                                    {s name="ConfirmAddressChooseDifferentShippingAddress"}{/s}
                                                                </a>
                                                            {/block}
                                                        </div>
                                                    {/block}
                                                </div>

                                                {block name='frontend_checkout_confirm_information_addresses_equal_panel_actions'}
                                                    <div class="panel--actions is--wide">
                                                        {block name="frontend_checkout_confirm_information_addresses_equal_panel_actions_change"}
                                                            <div class="address--actions-change">
                                                                {block name='frontend_checkout_confirm_information_addresses_equal_panel_shipping_change_address'}
                                                                    <a href="{url controller=address action=edit id=$billing.id sTarget=checkout sTargetAction=confirm}"
                                                                       data-address-editor="true"
                                                                       data-id="{$billing.id}"
                                                                       data-sessionKey="checkoutBillingAddressId,checkoutShippingAddressId"
                                                                       data-title="{s name="ConfirmAddressSelectButton"}Change address{/s}"
                                                                       title="{s name="ConfirmAddressSelectButton"}Change address{/s}"
                                                                       class="btn">
                                                                        {s name="ConfirmAddressSelectButton"}Change address{/s}
                                                                    </a>
                                                                {/block}

                                                                {block name='frontend_checkout_confirm_information_addresses_equal_panel_shipping_add_address'}
                                                                    <a href="{url controller=address}"
                                                                       class="btn choose-different-address"
                                                                       data-address-selection="true"
                                                                       data-sessionKey="checkoutShippingAddressId"
                                                                       data-id="{$shipping.id}"
                                                                       title="{s name="ConfirmAddressChooseDifferentShippingAddress"}{/s}">
                                                                        {s name="ConfirmAddressChooseDifferentShippingAddress"}{/s}
                                                                    </a>
                                                                {/block}
                                                            </div>
                                                        {/block}
                                                        {block name="frontend_checkout_confirm_information_addresses_equal_panel_actions_select_address"}
                                                            <a href="{url controller=address}"
                                                               title="{s name="ConfirmAddressSelectLink"}{/s}"
                                                               data-address-selection="true"
                                                               data-sessionKey="checkoutBillingAddressId,checkoutShippingAddressId"
                                                               data-id="{$billing.id}">
                                                                {s name="ConfirmAddressSelectLink"}{/s}
                                                            </a>
                                                        {/block}
                                                    </div>
                                                {/block}
                                            {/block}
                                        </div>
                                    {/block}
                                </div>
                            {/block}

                        {else}

                            {* Separate Billing & Shipping *}
                            {block name='frontend_checkout_confirm_information_addresses_billing'}
                                <div class="information--panel-item information--panel-item-billing">
                                    {* Billing address *}
                                    {block name='frontend_checkout_confirm_information_addresses_billing_panel'}
                                        <div class="panel has--border block information--panel billing--panel">

                                            {* Headline *}
                                            {block name='frontend_checkout_confirm_information_addresses_billing_panel_title'}
                                                <div class="panel--title is--underline">
                                                    {s name="ConfirmHeaderBilling" namespace="frontend/checkout/confirm"}{/s}
                                                </div>
                                            {/block}

                                            {* Content *}
                                            {block name='frontend_checkout_confirm_information_addresses_billing_panel_body'}
                                                <div class="panel--body is--wide">
                                                    {if $billing.company}
                                                    <span class="address--company is--bold">{$billing.company|escapeHtml}</span>{if $billing.department}<br /><span class="address--department is--bold">{$billing.department|escapeHtml}</span>{/if}
                                                        <br />
                                                    {/if}
                                                    <span class="address--salutation">{$billing.salutation|salutation}</span>
                                                    {if {config name="displayprofiletitle"}}
                                                        <span class="address--title">{$billing.title|escapeHtml}</span><br/>
                                                    {/if}
                                                    <span class="address--firstname">{$billing.firstname|escapeHtml}</span> <span class="address--lastname">{$billing.lastname|escapeHtml}</span><br />
                                                    <span class="address--street">{$billing.street|escapeHtml}</span><br />
                                                    {if $billing.additionalAddressLine1}<span class="address--additional-one">{$billing.additionalAddressLine1|escapeHtml}</span><br />{/if}
                                                    {if $billing.additionalAddressLine2}<span class="address--additional-two">{$billing.additionalAddressLine2|escapeHtml}</span><br />{/if}
                                                    {if {config name=showZipBeforeCity}}
                                                        <span class="address--zipcode">{$billing.zipcode|escapeHtml}</span> <span class="address--city">{$billing.city|escapeHtml}</span>
                                                    {else}
                                                        <span class="address--city">{$billing.city|escapeHtml}</span> <span class="address--zipcode">{$billing.zipcode|escapeHtml}</span>
                                                    {/if}<br />
                                                    {if $billing.state.name}<span class="address--statename">{$billing.state.name|escapeHtml}</span><br />{/if}
                                                    <span class="address--countryname">{$billing.country.name|escapeHtml}</span>

                                                    {block name="frontend_checkout_confirm_information_addresses_billing_panel_body_invalid_data"}
                                                        {if $invalidBillingAddress}
                                                            {include file='frontend/_includes/messages.tpl' type="warning" content="{s name='ConfirmAddressInvalidBillingAddress'}{/s}"}
                                                        {else}
                                                            {block name="frontend_checkout_confirm_information_addresses_billing_panel_body_set_as_default"}
                                                                {if $billing.id != $customer.defaultBillingAddress.id}
                                                                    <div class="set-default">
                                                                        <input type="checkbox" name="setAsDefaultBillingAddress" id="set_as_default_billing" value="1" /> <label for="set_as_default_billing">{s name="ConfirmUseForFutureOrders"}{/s}</label>
                                                                    </div>
                                                                {/if}
                                                            {/block}
                                                        {/if}
                                                    {/block}
                                                </div>
                                            {/block}

                                            {* Action buttons *}
                                            {block name="frontend_checkout_confirm_information_addresses_billing_panel_actions"}
                                                <div class="panel--actions is--wide">
                                                    {block name="frontend_checkout_confirm_information_addresses_billing_panel_actions_change"}
                                                        <div class="address--actions-change">
                                                            {block name="frontend_checkout_confirm_information_addresses_billing_panel_actions_change_address"}
                                                                <a href="{url controller=address action=edit id=$billing.id sTarget=checkout sTargetAction=confirm}"
                                                                   data-address-editor="true"
                                                                   data-sessionKey="checkoutBillingAddressId"
                                                                   data-id="{$billing.id}"
                                                                   data-title="{s name="ConfirmAddressSelectButton"}Change address{/s}"
                                                                   title="{s name="ConfirmAddressSelectButton"}Change address{/s}"
                                                                   class="btn">
                                                                    {s name="ConfirmAddressSelectButton"}Change address{/s}
                                                                </a>
                                                            {/block}
                                                        </div>
                                                    {/block}
                                                    {block name="frontend_checkout_confirm_information_addresses_billing_panel_actions_select_address"}
                                                        <a href="{url controller=address}"
                                                           data-address-selection="true"
                                                           data-sessionKey="checkoutBillingAddressId"
                                                           data-id="{$billing.id}"
                                                           title="{s name="ConfirmAddressSelectLink"}{/s}">
                                                            {s name="ConfirmAddressSelectLink"}{/s}
                                                        </a>
                                                    {/block}
                                                </div>
                                            {/block}
                                        </div>
                                    {/block}
                                </div>
                            {/block}

                            {block name='frontend_checkout_confirm_information_addresses_shipping'}
                                <div class="information--panel-item information--panel-item-shipping">
                                    {block name='frontend_checkout_confirm_information_addresses_shipping_panel'}
                                        <div class="panel has--border block information--panel shipping--panel">

                                            {* Headline *}
                                            {block name='frontend_checkout_confirm_information_addresses_shipping_panel_title'}
                                                <div class="panel--title is--underline">
                                                    {s name="ConfirmHeaderShipping" namespace="frontend/checkout/confirm"}{/s}
                                                </div>
                                            {/block}

                                            {* Content *}
                                            {block name='frontend_checkout_confirm_information_addresses_shipping_panel_body'}
                                                <div class="panel--body is--wide">
                                                    {if $shipping.company}
                                                        <span class="address--company is--bold">{$shipping.company|escapeHtml}</span>{if $shipping.department}<br /><span class="address--department is--bold">{$shipping.department|escapeHtml}</span>{/if}
                                                        <br />
                                                    {/if}

                                                    <span class="address--salutation">{$shipping.salutation|salutation}</span>
                                                    {if {config name="displayprofiletitle"}}
                                                        <span class="address--title">{$shipping.title|escapeHtml}</span><br/>
                                                    {/if}
                                                    <span class="address--firstname">{$shipping.firstname|escapeHtml}</span> <span class="address--lastname">{$shipping.lastname|escapeHtml}</span><br/>
                                                    <span class="address--street">{$shipping.street|escapeHtml}</span><br />
                                                    {if $shipping.additionalAddressLine1}<span class="address--additional-one">{$shipping.additionalAddressLine1|escapeHtml}</span><br />{/if}
                                                    {if $shipping.additionalAddressLine2}<span class="address--additional-one">{$shipping.additionalAddressLine2|escapeHtml}</span><br />{/if}
                                                    {if {config name=showZipBeforeCity}}
                                                        <span class="address--zipcode">{$shipping.zipcode|escapeHtml}</span> <span class="address--city">{$shipping.city|escapeHtml}</span>
                                                    {else}
                                                        <span class="address--city">{$shipping.city|escapeHtml}</span> <span class="address--zipcode">{$shipping.zipcode|escapeHtml}</span>
                                                    {/if}<br />
                                                    {if $shipping.state.name}<span class="address--statename">{$shipping.state.name|escapeHtml}</span><br />{/if}
                                                    <span class="address--countryname">{$shipping.country.name|escapeHtml}</span>

                                                    {block name="frontend_checkout_confirm_information_addresses_shipping_panel_body_invalid_data"}
                                                        {if $invalidShippingAddress}
                                                            {include file='frontend/_includes/messages.tpl' type="warning" content="{s name='ConfirmAddressInvalidShippingAddress'}{/s}"}
                                                        {else}
                                                            {block name="frontend_checkout_confirm_information_addresses_shipping_panel_body_set_as_default"}
                                                                {if $shipping.id != $customer.defaultShippingAddress.id}
                                                                    <div class="set-default">
                                                                        <input type="checkbox" name="setAsDefaultShippingAddress" id="set_as_default_shipping" value="1" /> <label for="set_as_default_shipping">{s name="ConfirmUseForFutureOrders"}{/s}</label>
                                                                    </div>
                                                                {/if}
                                                            {/block}
                                                        {/if}
                                                    {/block}
                                                </div>
                                            {/block}

                                            {* Action buttons *}
                                            {block name="frontend_checkout_confirm_information_addresses_shipping_panel_actions"}
                                                <div class="panel--actions is--wide">
                                                    {block name="frontend_checkout_confirm_information_addresses_shipping_panel_actions_change"}
                                                        <div class="address--actions-change">
                                                            {block name="frontend_checkout_confirm_information_addresses_shipping_panel_actions_change_address"}
                                                                <a href="{url controller=address action=edit id=$shipping.id sTarget=checkout sTargetAction=confirm}"
                                                                   title="{s name="ConfirmAddressSelectButton"}Change address{/s}"
                                                                   data-title="{s name="ConfirmAddressSelectButton"}Change address{/s}"
                                                                   data-address-editor="true"
                                                                   data-id="{$shipping.id}"
                                                                   data-sessionKey="checkoutShippingAddressId"
                                                                   class="btn">
                                                                    {s name="ConfirmAddressSelectButton"}Change address{/s}
                                                                </a>
                                                            {/block}
                                                        </div>
                                                    {/block}
                                                    {block name="frontend_checkout_confirm_information_addresses_shipping_panel_actions_select_address"}
                                                        <a href="{url controller=address}"
                                                           data-address-selection="true"
                                                           data-sessionKey="checkoutShippingAddressId"
                                                           data-id="{$shipping.id}"
                                                           title="{s name="ConfirmAddressSelectLink"}{/s}">
                                                            {s name="ConfirmAddressSelectLink"}{/s}
                                                        </a>
                                                    {/block}
                                                </div>
                                            {/block}
                                        </div>
                                    {/block}
                                </div>
                            {/block}
                        {/if}
                    {/block}

                    {$payment = $context.paymentMethod}
                    {$shippingMethod = $context.shippingMethod}

                    {* Payment method *}
                    {block name='frontend_checkout_confirm_information_payment'}
                        <div class="information--panel-item">
                            {block name='frontend_checkout_confirm_payment_method_panel'}
                                <div class="panel has--border block information--panel payment--panel">

                                    {block name='frontend_checkout_confirm_left_payment_method_headline'}
                                        <div class="panel--title is--underline payment--title">
                                            {s name="ConfirmHeaderPaymentShipping" namespace="frontend/checkout/confirm"}{/s}
                                        </div>
                                    {/block}

                                    {block name='frontend_checkout_confirm_left_payment_content'}
                                        <div class="panel--body is--wide payment--content">
                                            {block name='frontend_checkout_confirm_left_payment_method'}
                                                <p class="payment--method-info">
                                                    <strong class="payment--title">{s name="ConfirmInfoPaymentMethod" namespace="frontend/checkout/confirm"}{/s}</strong>
                                                    <span class="payment--description">{$payment.label}</span>
                                                </p>

                                                {if !$payment.esdActive && {config name="showEsd"}}
                                                    <p class="payment--confirm-esd">{s name="ConfirmInfoInstantDownload" namespace="frontend/checkout/confirm"}{/s}</p>
                                                {/if}
                                            {/block}

                                            {block name='frontend_checkout_confirm_left_shipping_method'}
                                                <p class="shipping--method-info">
                                                    <strong class="shipping--title">{s name="ConfirmHeadDispatch"}{/s}</strong>
                                                    <span class="shipping--description" title="{$shippingMethod.name}">{$shippingMethod.name|truncate:25:"...":true}</span>
                                                </p>
                                            {/block}
                                        </div>
                                    {/block}

                                    {block name='frontend_checkout_confirm_left_payment_method_actions'}
                                        <div class="panel--actions is--wide">
                                            {* Action buttons *}
                                            <a href="{url controller=checkout action=shippingPayment sTarget=checkout}" class="btn is--small btn--change-payment">
                                                {s name="ConfirmLinkChangePayment" namespace="frontend/checkout/confirm"}{/s}
                                            </a>
                                        </div>
                                    {/block}
                                </div>
                            {/block}
                        </div>
                    {/block}
                </div>
            {/block}
        </form>
    {/block}

    {* Additional feature which can be enabled / disabled in the base configuration *}
    {if {config name=commentvoucherarticle}||{config name=bonussystem} && {config name=bonus_system_active} && {config name=displaySlider}}
        {block name="frontend_checkout_confirm_additional_features"}
            <div class="panel has--border additional--features">
                {block name="frontend_checkout_confirm_additional_features_headline"}
                    <div class="panel--title is--underline">
                        {s name="ConfirmHeadlineAdditionalOptions"}{/s}
                    </div>
                {/block}

                {block name="frontend_checkout_confirm_additional_features_content"}
                    <div class="panel--body is--wide block-group">

                        {* Additional feature - Add voucher *}
                        {block name="frontend_checkout_confirm_additional_features_add_voucher"}
                            <div class="feature--group block">
                                <div class="feature--voucher">
                                    <form method="post" action="{url action='addVoucher' sTargetAction=$sTargetAction}" class="table--add-voucher add-voucher--form">
                                        {block name='frontend_checkout_table_footer_left_add_voucher_agb'}
                                            {if !{config name='IgnoreAGB'}}
                                                <input type="hidden" class="agb-checkbox" name="sAGB"
                                                       value="{if $sAGBChecked}1{else}0{/if}"/>
                                            {/if}
                                        {/block}

                                        {block name='frontend_checkout_confirm_add_voucher_field'}
                                            <input type="text" class="add-voucher--field block" name="code" placeholder="{"{s name='CheckoutFooterAddVoucherLabelInline' namespace='frontend/checkout/cart_footer'}{/s}"|escape}" />
                                        {/block}

                                        {block name='frontend_checkout_confirm_add_voucher_button'}
                                            <button type="submit" class="add-voucher--button btn is--primary is--small block">
                                                <i class="icon--arrow-right"></i>
                                            </button>
                                        {/block}
                                    </form>
                                </div>


                                {* Additional feature - Add product using the sku *}
                                {block name="frontend_checkout_confirm_additional_features_add_product"}
                                    <div class="feature--add-product">
                                        <form method="post" action="{url action='addArticle' sTargetAction=$sTargetAction}" class="table--add-product add-product--form block-group">

                                            {block name='frontend_checkout_confirm_add_product_field'}
                                                <input name="sAdd" class="add-product--field block" type="text" placeholder="{s name='CheckoutFooterAddProductPlaceholder' namespace='frontend/checkout/cart_footer_left'}{/s}" />
                                            {/block}

                                            {block name='frontend_checkout_confirm_add_product_button'}
                                                <button type="submit" class="add-product--button btn is--primary is--small block">
                                                    <i class="icon--arrow-right"></i>
                                                </button>
                                            {/block}
                                        </form>
                                    </div>
                                {/block}
                            </div>
                        {/block}

                        {* Additional customer comment for the order *}
                        {block name='frontend_checkout_confirm_comment'}
                            <div class="feature--user-comment block">
                                <textarea class="user-comment--field" rows="5" cols="20" placeholder="{s name="ConfirmPlaceholderComment" namespace="frontend/checkout/confirm"}{/s}" data-pseudo-text="true" data-selector=".user-comment--hidden">{$sComment|escape}</textarea>
                            </div>
                        {/block}
                    </div>
                {/block}
            </div>
        {/block}
    {/if}

    {* Premiums articles *}
    {block name='frontend_checkout_confirm_premiums'}
        {if $sPremiums && {config name=premiumarticles}}
            {include file='frontend/checkout/premiums.tpl'}
        {/if}
    {/block}

    {block name='frontend_checkout_confirm_product_table'}
        <div class="product--table">
            <div class="panel has--border">
                <div class="panel--body is--rounded">

                    {* Product table header *}
                    {block name='frontend_checkout_confirm_confirm_head'}
                        {include file="frontend/checkout/confirm_header.tpl"}
                    {/block}

                    {block name='frontend_checkout_confirm_item_before'}{/block}

                    {* Basket items *}
                    {block name='frontend_checkout_confirm_item_outer'}
                        {foreach $cart.viewLineItems.elements as $lineItem}
                            {block name='frontend_checkout_confirm_item'}
                                {include file='frontend/checkout/confirm_item.tpl' isLast=$lineItem@last}
                            {/block}
                        {/foreach}
                    {/block}

                    {block name='frontend_checkout_confirm_item_after'}{/block}

                    {* Table footer *}
                    {block name='frontend_checkout_confirm_confirm_footer'}
                        {include file="frontend/checkout/confirm_footer.tpl"}
                    {/block}
                </div>
            </div>

            {* Table actions *}
            {block name='frontend_checkout_confirm_confirm_table_actions'}
                <div class="table--actions actions--bottom">
                    <div class="main--actions">
                        {if $sLaststock.hideBasket}
                            {block name='frontend_checkout_confirm_stockinfo'}
                                {include file="frontend/_includes/messages.tpl" type="error" content="{s name='ConfirmErrorStock'}{/s}"}
                            {/block}
                        {elseif ($invalidBillingAddress || $invalidShippingAddress)}
                            {block name='frontend_checkout_confirm_addressinfo'}
                                {include file="frontend/_includes/messages.tpl" type="error" content="{s name='ConfirmErrorInvalidAddress'}{/s}"}
                            {/block}
                        {else}
                            {block name='frontend_checkout_confirm_submit'}
                                {* Submit order button *}
                                {if $sPayment.embediframe || $sPayment.action}
                                    <button type="submit" class="btn is--primary is--large right is--icon-right" form="confirm--form" data-preloader-button="true">
                                        {s name='ConfirmDoPayment'}{/s}<i class="icon--arrow-right"></i>
                                    </button>
                                {else}
                                    <button type="submit" class="btn is--primary is--large right is--icon-right" form="confirm--form" data-preloader-button="true">
                                        {s name='ConfirmActionSubmit'}{/s}<i class="icon--arrow-right"></i>
                                    </button>
                                {/if}
                            {/block}
                        {/if}
                    </div>
                </div>
            {/block}
        </div>
    {/block}
</div>
{/block}
