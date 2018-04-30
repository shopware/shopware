{extends file="frontend/index/index.tpl"}

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
{/block}

{* Hide top bar *}
{block name='frontend_index_top_bar_container'}
    {if !$theme.checkoutHeader}
        {$smarty.block.parent}
    {/if}
{/block}

{* Footer *}
{block name='frontend_index_footer'}
    {if !$theme.checkoutFooter}
        {$smarty.block.parent}
    {else}
        {block name='frontend_index_checkout_finish_footer'}
            {include file="frontend/index/footer_minimal.tpl"}
        {/block}
    {/if}
{/block}

{* Back to the shop button *}
{block name='frontend_index_logo_trusted_shops' append}
    {if $theme.checkoutHeader}
        <a href="{url controller='index'}"
           class="btn is--small btn--back-top-shop is--icon-left"
           title="{"{s name='FinishButtonBackToShop'}{/s}"|escape}">
            <i class="icon--arrow-left"></i>
            {s name="FinishButtonBackToShop"}{/s}
        </a>
    {/if}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div class="content checkout--content finish--content">

        {* Finish teaser message *}
        {block name='frontend_checkout_finish_teaser'}
            <div class="finish--teaser panel has--border is--rounded">

                {block name='frontend_checkout_finish_teaser_title'}
                    <h2 class="panel--title teaser--title is--align-center">{s name="FinishHeaderThankYou"}{/s} {$sShopname|escapeHtml}!</h2>
                {/block}

                {block name='frontend_checkout_finish_teaser_content'}
                    <div class="panel--body is--wide is--align-center">
                        {if $confirmMailDeliveryFailed}
                            {include file="frontend/_includes/messages.tpl" type="error" content="{s name="FinishInfoConfirmationMailFailed"}{/s}"}
                        {/if}

                        <p class="teaser--text">
                            {if !$confirmMailDeliveryFailed}
                                {s name="FinishInfoConfirmationMail"}{/s}
                                <br />
                            {/if}

                            {s name="FinishInfoPrintOrder"}{/s}
                        </p>

                        {block name='frontend_checkout_finish_teaser_actions'}
                            <p class="teaser--actions">

                                {strip}
                                {* Back to the shop button *}
                                <a href="{url controller='index'}" class="btn is--secondary teaser--btn-back is--icon-left" title="{"{s name='FinishButtonBackToShop'}{/s}"|escape}">
                                    <i class="icon--arrow-left"></i>&nbsp;{"{s name="FinishButtonBackToShop"}{/s}"|replace:' ':'&nbsp;'}
                                </a>

                                {* Print button *}
                                <a href="#" class="btn is--primary teaser--btn-print" onclick="self.print()" title="{"{s name='FinishLinkPrint'}{/s}"|escape}">
                                    {s name="FinishLinkPrint"}{/s}
                                </a>
                                {/strip}
                            </p>

                            {* Print notice *}
                            {block name='frontend_checkout_finish_teaser_print_notice'}
                                <p class="print--notice">
                                    {s name="FinishPrintNotice"}{/s}
                                </p>
                            {/block}
                        {/block}
                    </div>
                {/block}
            </div>
        {/block}

        {block name='frontend_checkout_finish_information_wrapper'}
            {$billing = $context.customer.activeBillingAddress}
            {$shipping = $context.customer.activeShippingAddress}
            {$customer = $context.customer}

            <div class="panel--group block-group information--panel-wrapper finish--info" data-panel-auto-resizer="true">

                {block name='frontend_checkout_finish_information_addresses'}

                    {if $billing.id == $shipping.id}

                        {* Equal Billing & Shipping *}
                        {block name='frontend_checkout_finish_information_addresses_equal'}
                            <div class="information--panel-item information--panel-address">

                                {block name='frontend_checkout_finish_information_addresses_equal_panel'}
                                    <div class="panel has--border is--rounded block information--panel finish--billing">

                                        {block name='frontend_checkout_finish_information_addresses_equal_panel_title'}
                                            <div class="panel--title is--underline">
                                                {s name='ConfirmAddressEqualTitle' namespace="frontend/checkout/confirm"}{/s}
                                            </div>
                                        {/block}

                                        {block name='frontend_checkout_finish_information_addresses_equal_panel_body'}
                                            <div class="panel--body is--wide">

                                                {block name='frontend_checkout_finish_information_addresses_equal_panel_billing'}
                                                    <div class="billing--panel">
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
                                                    </div>
                                                {/block}
                                            </div>
                                        {/block}
                                    </div>
                                {/block}
                            </div>
                        {/block}

                    {else}

                        {* Separate Billing & Shipping *}
                        {block name='frontend_checkout_finish_information_addresses_billing'}
                            <div class="information--panel-item information--panel-item-billing">
                                {* Billing address *}
                                {block name='frontend_checkout_finish_information_addresses_billing_panel'}
                                    <div class="panel has--border block information--panel billing--panel finish--billing">

                                        {* Headline *}
                                        {block name='frontend_checkout_confirm_information_addresses_billing_panel_title'}
                                            <div class="panel--title is--underline">
                                                {s name="ConfirmHeaderBilling" namespace="frontend/checkout/confirm"}{/s}
                                            </div>
                                        {/block}

                                        {* Content *}
                                        {block name='frontend_checkout_finish_information_addresses_billing_panel_body'}
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
                                                {if $billing.state.name|escapeHtml}<span class="address--statename">{$billing.state.name|escapeHtml}</span><br />{/if}
                                                <span class="address--countryname">{$billing.country.name|escapeHtml}</span>
                                            </div>
                                        {/block}
                                    </div>
                                {/block}
                            </div>
                        {/block}

                        {block name='frontend_checkout_finish_information_addresses_shipping'}
                            <div class="information--panel-item information--panel-item-shipping">
                                {block name='frontend_checkout_finish_information_addresses_shipping_panel'}
                                    <div class="panel has--border block information--panel shipping--panel finish--shipping">

                                        {* Headline *}
                                        {block name='frontend_checkout_finish_information_addresses_shipping_panel_title'}
                                            <div class="panel--title is--underline">
                                                {s name="ConfirmHeaderShipping" namespace="frontend/checkout/confirm"}{/s}
                                            </div>
                                        {/block}

                                        {* Content *}
                                        {block name='frontend_checkout_finish_information_addresses_shipping_panel_body'}
                                            <div class="panel--body is--wide">
                                                {if $shipping.company}
                                                    <span class="address--company is--bold">{$shipping.company|escapeHtml}</span>{if $shipping.department}<br /><span class="address--department is--bold">{$shipping.department|escapeHtml}</span>{/if}
                                                    <br />
                                                {/if}

                                                <span class="address--salutation">{$shipping.salutation|salutation}</span>
                                                {if {config name="displayprofiletitle"}}
                                                    <span class="address--title">{$shipping.title|escapeHtml}</span><br/>
                                                {/if}
                                                <span class="address--firstname">{$shipping.firstname|escapeHtml}</span> <span class="address--lastname">{$shipping.lastname|escapeHtml}</span><br />
                                                <span class="address--street">{$shipping.street|escapeHtml}</span><br />
                                                {if $shipping.additionalAddressLine1}<span class="address--additional-one">{$shipping.additionalAddressLine1|escapeHtml}</span><br />{/if}
                                                {if $shipping.additionalAddressLine2}<span class="address--additional-two">{$shipping.additionalAddressLine2|escapeHtml}</span><br />{/if}
                                                {if {config name=showZipBeforeCity}}
                                                    <span class="address--zipcode">{$shipping.zipcod|escapeHtml}</span> <span class="address--city">{$shipping.city|escapeHtml}</span>
                                                {else}
                                                    <span class="address--city">{$shipping.city|escapeHtml}</span> <span class="address--zipcode">{$shipping.zipcode|escapeHtml}</span>
                                                {/if}<br />
                                                {if $shipping.state.name}<span class="address--statename">{$shipping.state.name|escapeHtml}</span><br />{/if}
                                                <span class="address--countryname">{$shipping.country.name|escapeHtml}</span>
                                            </div>
                                        {/block}
                                    </div>
                                {/block}
                            </div>
                        {/block}
                    {/if}
                {/block}

                {* Payment method *}
                {block name='frontend_checkout_finish_information_payment'}
                    <div class="information--panel-item">
                        {block name='frontend_checkout_finish_payment_method_panel'}
                            <div class="panel has--border block information--panel payment--panel finish--details">

                                {block name='frontend_checkout_finish_left_payment_method_headline'}
                                    <div class="panel--title is--underline payment--title">
                                        {s name="FinishHeaderInformation"}{/s}
                                    </div>
                                {/block}

                                {block name='frontend_checkout_finish_left_payment_content'}
                                    <div class="panel--body is--wide payment--content">

                                        {* Invoice number *}
                                        {block name='frontend_checkout_finish_invoice_number'}
                                            {if $sOrderNumber}
                                                <strong>{s name="FinishInfoId"}{/s}</strong> {$sOrderNumber}<br />
                                            {/if}
                                        {/block}

                                        {* Transaction number *}
                                        {block name='frontend_checkout_finish_transaction_number'}
                                            {if $sTransactionumber}
                                                <strong>{s name="FinishInfoTransaction"}{/s}</strong> {$sTransactionumber}<br />
                                            {/if}
                                        {/block}

                                        {$payment = $context.paymentMethod}
                                        {$shippingMethod = $context.shippingMethod}

                                        {* Payment method *}
                                        {block name='frontend_checkout_finish_payment_method'}
                                            {if $payment.label}
                                                <strong>{s name="ConfirmHeaderPayment" namespace="frontend/checkout/confirm"}{/s}:</strong> {$payment.label}<br />
                                            {/if}
                                        {/block}

                                        {* Dispatch method *}
                                        {block name='frontend_checkout_finish_dispatch_method'}
                                            {if $shippingMethod.name}
                                                <strong>{s name="CheckoutDispatchHeadline" namespace="frontend/checkout/confirm_dispatch"}{/s}:</strong> {$shippingMethod.name}
                                            {/if}
                                        {/block}

                                    </div>
                                {/block}

                            </div>
                        {/block}
                    </div>
                {/block}
            </div>
        {/block}

        {block name='frontend_checkout_finish_items'}
            <div class="finish--table product--table">
                <div class="panel has--border">
                    <div class="panel--body is--rounded">

                        {* Table header *}
                        {block name='frontend_checkout_finish_table_header'}
                            {include file="frontend/checkout/finish_header.tpl"}
                        {/block}

                        {* Article items *}
                        {foreach $cart.viewLineItems.elements as $lineItem}
                            {block name='frontend_checkout_finish_item'}
                                {include file='frontend/checkout/finish_item.tpl' isLast=$lineItem@last}
                            {/block}
                        {/foreach}

                        {* Table footer *}
                        {block name='frontend_checkout_finish_table_footer'}
                            {include file="frontend/checkout/finish_footer.tpl"}
                        {/block}
                    </div>
                </div>
            </div>
        {/block}
    </div>
{/block}
