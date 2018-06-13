{block name='frontend_checkout_ajax_cart'}
    <div class="ajax--cart">
        {block name='frontend_checkout_ajax_cart_buttons_offcanvas'}
            <div class="buttons--off-canvas">
                {block name='frontend_checkout_ajax_cart_buttons_offcanvas_inner'}
                    <a href="#close-categories-menu" class="close--off-canvas">
                        <i class="icon--arrow-left"></i>
                        {s name="AjaxCartContinueShopping"}{/s}
                    </a>
                {/block}
            </div>
        {/block}

        {block name='frontend_checkout_ajax_cart_alert_box'}
            {if $theme.offcanvasCart}
                {if $basketInfoMessage}
                    <div class="alert is--info is--rounded is--hidden">
                        <div class="alert--icon">
                            <div class="icon--element icon--info"></div>
                        </div>
                        <div class="alert--content">{$basketInfoMessage}</div>
                    </div>
                {else}
                    <div class="alert is--success is--rounded is--hidden">
                        <div class="alert--icon">
                            <div class="icon--element icon--check"></div>
                        </div>
                        <div class="alert--content">{s name="AjaxCartSuccessText" namespace="frontend/checkout/ajax_cart"}{/s}</div>
                    </div>
                {/if}
            {/if}
        {/block}

        {block name='frontend_checkout_ajax_cart_item_container'}
            <div class="item--container">
                {block name='frontend_checkout_ajax_cart_item_container_inner'}
                    {if $cart.viewLineItems.elements}
                        {foreach $cart.viewLineItems.elements as $lineItem}
                            {block name='frontend_checkout_ajax_cart_row'}
                                {include file="frontend/checkout/ajax_cart_item.tpl" lineItem=$lineItem}
                            {/block}
                        {/foreach}
                    {else}
                        {block name='frontend_checkout_ajax_cart_empty'}
                            <div class="cart--item is--empty">
                                {block name='frontend_checkout_ajax_cart_empty_inner'}
                                    <span class="cart--empty-text">{s name='AjaxCartInfoEmpty'}{/s}</span>
                                {/block}
                            </div>
                        {/block}
                    {/if}
                {/block}
            </div>
        {/block}

        {block name='frontend_checkout_ajax_cart_prices_container'}
            {if $cart.viewLineItems.elements}
                <div class="prices--container">
                    {block name='frontend_checkout_ajax_cart_prices_container_inner'}
                        <div class="prices--articles">
                            <span class="prices--articles-text">{s name="AjaxCartTotalAmount"}{/s}</span>
                            <span class="prices--articles-amount">{$cart.calculatedCart.price.totalPrice|currency}</span>
                        </div>
                    {/block}
                </div>
            {/if}
        {/block}

        {* Basket link *}
        {block name='frontend_checkout_ajax_cart_button_container'}
            <div class="button--container">
                {block name='frontend_checkout_ajax_cart_button_container_inner'}
                    {if !($sDispatchNoOrder && !$sDispatches)}
                        {block name='frontend_checkout_ajax_cart_open_checkout'}
                            <a href="{if {config name=always_select_payment}}{url controller='checkout' action='shippingPayment'}{else}{url controller='checkout' action='confirm'}{/if}" class="btn is--primary button--checkout is--icon-right" title="{"{s name='AjaxCartLinkConfirm'}{/s}"|escape}">
                                <i class="icon--arrow-right"></i>
                                {s name='AjaxCartLinkConfirm'}{/s}
                            </a>
                        {/block}
                    {else}
                        {block name='frontend_checkout_ajax_cart_open_checkout'}
                            <span class="btn is--disabled is--primary button--checkout is--icon-right" title="{"{s name='AjaxCartLinkConfirm'}{/s}"|escape}">
                                <i class="icon--arrow-right"></i>
                                {s name='AjaxCartLinkConfirm'}{/s}
                            </span>
                        {/block}
                    {/if}
                    {block name='frontend_checkout_ajax_cart_open_basket'}
                        <a href="{url controller='checkout' action='cart'}" class="btn button--open-basket is--icon-right" title="{"{s name='AjaxCartLinkBasket'}{/s}"|escape}">
                            <i class="icon--arrow-right"></i>
                            {s name='AjaxCartLinkBasket'}{/s}
                        </a>
                    {/block}
                {/block}
            </div>
        {/block}
    </div>
{/block}