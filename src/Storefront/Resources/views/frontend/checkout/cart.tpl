{extends file='frontend/index/index.tpl'}

{* Title *}
{block name='frontend_index_header_title'}
    {s name="CartTitle"}{/s} | {{config name=shopName}|escapeHtml}
{/block}

{* Hide breadcrumb *}
{block name='frontend_index_breadcrumb'}{/block}

{* Step Box *}
{block name="frontend_index_content_top"}{/block}

{* Main content *}
{block name='frontend_index_content'}
    <div class="content content--basket content--checkout">

        {* If articles are in the basket... *}
        {if $cart.viewLineItems.elements}

            {* Add article informations *}
            {block name='frontend_checkout_add_article'}
                <noscript>
                    {include file='frontend/checkout/added.tpl'}
                </noscript>
            {/block}

            {* Product table *}
            {block name='frontend_checkout_cart_table'}
                <div class="product--table{if {config name=BasketShippingInfo}} has--dispatch-info{/if}">

                    {* Deliveryfree dispatch notification *}
                    {block name='frontend_checkout_cart_deliveryfree'}
                        {if $sShippingcostsDifference}
                            {$shippingDifferenceContent="<strong>{s name='CartInfoFreeShipping'}{/s}</strong> {s name='CartInfoFreeShippingDifference'}{/s}"}
                            {include file="frontend/_includes/messages.tpl" type="warning" content="{$shippingDifferenceContent}"}
                        {/if}
                    {/block}

                    {* Error messages *}
                    {block name='frontend_checkout_cart_error_messages'}
                        {include file="frontend/checkout/error_messages.tpl"}
                    {/block}

                    {block name='frontend_checkout_cart_table_actions'}
                        <div class="table--actions">

                            <div class="main--actions">
                                {* Continue shopping *}
                                {block name="frontend_checkout_actions_link_last"}{/block}

                                {block name="frontend_checkout_actions_confirm"}

                                    {* Forward to the checkout *}
                                    {if !$sMinimumSurcharge && !($sDispatchNoOrder && !$sDispatches)}
                                        {block name="frontend_checkout_actions_checkout"}
                                            <a href="{if {config name=always_select_payment}}{url controller='checkout' action='shippingPayment'}{else}{url controller='checkout' action='confirm'}{/if}"
                                               title="{"{s name='CheckoutActionsLinkProceedShort' namespace="frontend/checkout/actions"}{/s}"|escape}"
                                               class="btn btn--checkout-proceed is--primary right is--icon-right is--large">
                                                {s name="CheckoutActionsLinkProceedShort" namespace="frontend/checkout/actions"}{/s}
                                                <i class="icon--arrow-right"></i>
                                            </a>
                                        {/block}
                                    {else}
                                        {block name="frontend_checkout_actions_checkout"}
                                            <span
                                               title="{"{s name='CheckoutActionsLinkProceedShort' namespace="frontend/checkout/actions"}{/s}"|escape}"
                                               class="btn is--disabled btn--checkout-proceed is--primary right is--icon-right is--large">
                                                {s name="CheckoutActionsLinkProceedShort" namespace="frontend/checkout/actions"}{/s}
                                                <i class="icon--arrow-right"></i>
                                            </span>
                                        {/block}
                                    {/if}
                                {/block}
                            </div>
                        </div>
                    {/block}

                    {* Product table content *}
                    {block name='frontend_checkout_cart_panel'}
                        <div class="panel has--border">
                            <div class="panel--body is--rounded">

                                {* Product table header *}
                                {block name='frontend_checkout_cart_cart_head'}
                                    {include file="frontend/checkout/cart_header.tpl"}
                                {/block}

                                {* Basket items *}
                                {foreach $cart.viewLineItems.elements as $lineItem}
                                    {block name='frontend_checkout_cart_item'}
                                        {include file='frontend/checkout/cart_item.tpl' isLast=$lineItem@last}
                                    {/block}
                                {/foreach}

                                {* Product table footer *}
                                {block name='frontend_checkout_cart_cart_footer'}
                                    {include file="frontend/checkout/cart_footer.tpl"}
                                {/block}
                            </div>
                        </div>
                    {/block}

                    {* Premium products *}
                    {block name='frontend_checkout_cart_premium'}
                        {if $sPremiums}

                            {* Actual listing *}
                            {block name='frontend_checkout_cart_premium_products'}
                                {include file='frontend/checkout/premiums.tpl'}
                            {/block}
                        {/if}
                    {/block}

                    {block name='frontend_checkout_cart_table_actions_bottom'}
                        <div class="table--actions actions--bottom">
                            {block name="frontend_checkout_actions_confirm_bottom"}
                                <div class="main--actions">

                                    {* Continue shopping *}
                                    {block name="frontend_checkout_actions_link_last_bottom"}{/block}

                                    {* Forward to the checkout *}
                                    {if !$sMinimumSurcharge && !($sDispatchNoOrder && !$sDispatches)}
                                        {block name="frontend_checkout_actions_confirm_bottom_checkout"}
                                            <a href="{if {config name=always_select_payment}}{url controller='checkout' action='shippingPayment'}{else}{url controller='checkout' action='confirm'}{/if}"
                                               title="{"{s name='CheckoutActionsLinkProceedShort' namespace="frontend/checkout/actions"}{/s}"|escape}"
                                               class="btn btn--checkout-proceed is--primary right is--icon-right is--large">
                                                {s name="CheckoutActionsLinkProceedShort" namespace="frontend/checkout/actions"}{/s}
                                                <i class="icon--arrow-right"></i>
                                            </a>
                                        {/block}
                                    {else}
                                        {block name="frontend_checkout_actions_confirm_bottom_checkout"}
                                            <span
                                               title="{"{s name='CheckoutActionsLinkProceedShort' namespace="frontend/checkout/actions"}{/s}"|escape}"
                                               class="btn is--disabled btn--checkout-proceed is--primary right is--icon-right is--large">
                                                {s name="CheckoutActionsLinkProceedShort" namespace="frontend/checkout/actions"}{/s}
                                                <i class="icon--arrow-right"></i>
                                            </span>
                                        {/block}
                                    {/if}
                                </div>

                                {if !$sMinimumSurcharge && ($sInquiry || $sDispatchNoOrder)}
                                    {block name="frontend_checkout_actions_inquiry"}
                                        <a href="{$sInquiryLink}"
                                           title="{"{s name='CheckoutActionsLinkOffer' namespace="frontend/checkout/actions"}{/s}"|escape}"
                                           class="btn btn--inquiry is--large is--full is--center">
                                            {s name="CheckoutActionsLinkOffer" namespace="frontend/checkout/actions"}{/s}
                                        </a>
                                    {/block}
                                {/if}
                            {/block}
                        </div>
                    {/block}

                    {* Benefit and services footer *}
                    {block name="frontend_checkout_footer"}
                        {include file="frontend/checkout/table_footer.tpl"}
                    {/block}
                </div>
            {/block}

        {else}
            {* Empty basket *}
            {block name='frontend_basket_basket_is_empty'}
                <div class="basket--info-messages">
                    {include file="frontend/_includes/messages.tpl" type="warning" content="{s name='CartInfoEmpty'}{/s}"}
                </div>
            {/block}
        {/if}
    </div>
{/block}
