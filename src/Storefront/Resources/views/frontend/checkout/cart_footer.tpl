{* Add product using the sku *}
{block name='frontend_checkout_cart_footer_add_product'}
    <form method="post" action="{url action='addProduct' sTargetAction=$sTargetAction}" class="table--add-product add-product--form block-group">

        {block name='frontend_checkout_cart_footer_add_product_field'}
            <input name="number" class="add-product--field block" type="text" placeholder="{s name='CheckoutFooterAddProductPlaceholder' namespace='frontend/checkout/cart_footer_left'}{/s}" />
        {/block}

        {block name='frontend_checkout_cart_footer_add_product_button'}
            <button type="submit" class="add-product--button btn is--primary is--center block">
                <i class="icon--arrow-right"></i>
            </button>
        {/block}
    </form>
{/block}

{block name='frontend_checkout_cart_footer_element'}
    <div class="basket--footer">
        <div class="table--aggregation">
            {* Add product using a voucher *}
            {block name='frontend_checkout_cart_footer_add_voucher'}
                <form method="post" action="{url action='addVoucher' sTargetAction=$sTargetAction}" class="table--add-voucher add-voucher--form">

                    {block name='frontend_checkout_cart_footer_add_voucher_trigger'}
                        <input type="checkbox" id="add-voucher--trigger" class="add-voucher--checkbox">
                    {/block}

                    {block name='frontend_checkout_cart_footer_add_voucher_label'}
                        <label for="add-voucher--trigger" class="add-voucher--label">{s name="CheckoutFooterVoucherTrigger"}{/s}</label>
                    {/block}

                    <div class="add-voucher--panel is--hidden block-group">
                        {block name='frontend_checkout_cart_footer_add_voucher_field'}
                            <input type="text" class="add-voucher--field is--medium block" name="code" placeholder="{"{s name='CheckoutFooterAddVoucherLabelInline'}{/s}"|escape}" />
                        {/block}

                        {block name='frontend_checkout_cart_footer_add_voucher_button'}
                            <button type="submit" class="add-voucher--button is--medium btn is--primary is--center block">
                                <i class="icon--arrow-right"></i>
                            </button>
                        {/block}
                    </div>
                </form>
            {/block}

            {* Shipping costs pre-calculation *}
            {if $sBasket.content && !$sUserLoggedIn && !$sUserData.additional.user.id && {config name=basketShowCalculation}}

                {block name='frontend_checkout_shipping_costs_country_trigger'}
                    <a href="#show-hide--shipping-costs" class="table--shipping-costs-trigger">
                        {s name='CheckoutFooterEstimatedShippingCosts'}{/s} <i class="icon--arrow-right"></i>
                    </a>
                {/block}

                {block name='frontend_checkout_shipping_costs_country_include'}
                    {include file="frontend/checkout/shipping_costs.tpl"}
                {/block}
            {/if}
        </div>

        {block name='frontend_checkout_cart_footer_field_labels'}
            <ul class="aggregation--list">

                {* Basket sum *}
                {block name='frontend_checkout_cart_footer_field_labels_sum'}
                    <li class="list--entry block-group entry--sum">

                        {block name='frontend_checkout_cart_footer_field_labels_sum_label'}
                            <div class="entry--label block">
                                {s name="CartFooterLabelSum"}{/s}
                            </div>
                        {/block}

                        {block name='frontend_checkout_cart_footer_field_labels_sum_value'}
                            <div class="entry--value block">
                                {$cart.calculatedCart.price.positionPrice|currency}{s name="Star" namespace="frontend/listing/box_article"}{/s}
                            </div>
                        {/block}
                    </li>
                {/block}

                {* Shipping costs *}
                {block name='frontend_checkout_cart_footer_field_labels_shipping'}
                    <li class="list--entry block-group entry--shipping">

                        {block name='frontend_checkout_cart_footer_field_labels_shipping_label'}
                            <div class="entry--label block">
                                {s name="CartFooterLabelShipping"}{/s}
                            </div>
                        {/block}

                        {block name='frontend_checkout_cart_footer_field_labels_shipping_value'}
                            <div class="entry--value block">
                                {$cart.calculatedCart.shippingCosts|currency}{s name="Star" namespace="frontend/listing/box_article"}{/s}
                            </div>
                        {/block}
                    </li>
                {/block}

                {* Total sum *}
                {block name='frontend_checkout_cart_footer_field_labels_total'}
                    <li class="list--entry block-group entry--total">

                        {block name='frontend_checkout_cart_footer_field_labels_total_label'}
                            <div class="entry--label block">
                                {s name="CartFooterLabelTotal"}{/s}
                            </div>
                        {/block}

                        {block name='frontend_checkout_cart_footer_field_labels_total_value'}
                            <div class="entry--value block is--no-star">
                                {$cart.calculatedCart.price.totalPrice|currency}
                            </div>
                        {/block}
                    </li>
                {/block}

                {* Total net *}
                {block name='frontend_checkout_cart_footer_field_labels_totalnet'}
                    {*{if $sUserData.additional.charge_vat}*}
                        <li class="list--entry block-group entry--totalnet">

                            {block name='frontend_checkout_cart_footer_field_labels_totalnet_label'}
                                <div class="entry--label block">
                                    {s name="CartFooterTotalNet"}{/s}
                                </div>
                            {/block}

                            {block name='frontend_checkout_cart_footer_field_labels_totalnet_value'}
                                <div class="entry--value block is--no-star">
                                    {$cart.calculatedCart.price.netPrice|currency}
                                </div>
                            {/block}
                        </li>
                    {*{/if}*}
                {/block}

                {* Taxes *}
                {block name='frontend_checkout_cart_footer_field_labels_taxes'}

                    {foreach $cart.calculatedCart.price.calculatedTaxes.elements as $rule}
                        {$value = $rule.tax}
                        {$rate = $rule.taxRate}

                        {block name='frontend_checkout_cart_footer_field_labels_taxes_entry'}
                            <li class="list--entry block-group entry--taxes">

                                {block name='frontend_checkout_cart_footer_field_labels_taxes_label'}
                                    <div class="entry--label block">
                                        {s name="CartFooterTotalTax"}{/s}
                                    </div>
                                {/block}

                                {block name='frontend_checkout_cart_footer_field_labels_taxes_value'}
                                    <div class="entry--value block is--no-star">
                                        {$value|currency}
                                    </div>
                                {/block}
                            </li>
                        {/block}
                    {/foreach}
                {/block}
            </ul>
        {/block}
    </div>
{/block}