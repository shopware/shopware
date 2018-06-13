{block name='frontend_checkout_premium_body'}

    {if $sPremiums|@count}
        <div class="premium-product panel has--border is--rounded">

            {* Headline *}
            {block name='frontend_checkout_cart_premium_headline'}
                <div class="premium-product--title panel--title is--underline">
                    {s name="CartPremiumsHeadline" namespace="frontend/checkout/cart"}{/s}
                </div>
            {/block}

            {* Product slider *}
            {block name='frontend_checkout_premium_slider'}
                <div class="premium-product--content product-slider" data-product-slider="true" data-itemMinWidth="280">

                    {* Product slider container *}
                    {block name='frontend_checkout_premium_slider_container'}
                        <div class="product-slider--container">
                            {foreach $sPremiums as $premium}

                                {* Product slider item *}
                                {block name='frontend_checkout_premium_slider_item'}
                                    <div class="premium-product--product product-slider--item">

                                        <div class="product--inner">
                                            {if $premium.available}
                                                {block name='frontend_checkout_premium_info_free'}
                                                    <p class="premium-product--free">{s name="PremiumInfoFreeProduct"}{/s}</p>
                                                {/block}
                                            {else}
                                                {block name='frontend_checkout_premium_info_difference'}
                                                    <p class="premium-product--info">{s name="PremiumsInfoAtAmount"}{/s} {$premium.startprice|currency} {s name="PremiumInfoBasketValue"}{/s}</p>
                                                {/block}
                                            {/if}

                                            {* Product image *}
                                            {block name='frontend_checkout_premium_image'}
                                                <a href="{$premium.sArticle.linkDetails}" title="{$premium.sArticle.articleName|escape}" class="product--image">
                                                    {if $premium.available}
                                                        <div class="premium-product--badge">
                                                            <i class="icon--check"></i>
                                                        </div>
                                                    {/if}

                                                    {block name='frontend_checkout_premium_image_element'}
                                                        <span class="image--element">
                                                            {if $premium.sArticle.image.thumbnails}
                                                                <img srcset="{$premium.sArticle.image.thumbnails[0].sourceSet}"
                                                                     alt="{$premium.sArticle.articleName|escape}" />
                                                            {else}
                                                                <img src="{link file='frontend/_public/src/img/no-picture.jpg'}"
                                                                     alt="{"{s name="PremiumInfoNoPicture"}{/s}"|escape}">
                                                            {/if}
                                                        </span>
                                                    {/block}
                                                </a>
                                            {/block}

                                            {if $premium.available}
                                                {block name='frontend_checkout_premium_form'}
                                                    <form action="{url action='addPremium' sTargetAction=$sTargetAction}" method="post" id="sAddPremiumForm{$key}" name="sAddPremiumForm{$key}">
                                                        {block name='frontend_checkout_premium_select_article'}
                                                            {if $premium.sVariants && $premium.sVariants|@count > 1}
                                                                <div class="premium--variant">
                                                                    <div class="select-field">
                                                                        <select class="premium--selection" id="sAddPremium{$key}" name="sAddPremium" required>
                                                                            <option value="">{s name="PremiumInfoSelect"}{/s}</option>
                                                                            {foreach from=$premium.sVariants item=variant}
                                                                                <option value="{$variant.ordernumber}">{$variant.additionaltext}</option>
                                                                            {/foreach}
                                                                        </select>
                                                                    </div>
                                                                    {block name='frontend_checkout_premium_info_button_small'}
                                                                        <button class="premium--button btn is--primary is--align-center" type="submit">
                                                                            <i class="icon--arrow-right is--large"></i>
                                                                        </button>
                                                                    {/block}
                                                                </div>
                                                            {else}
                                                                <input type="hidden" name="sAddPremium" value="{$premium.sArticle.ordernumber}"/>
                                                                {block name='frontend_checkout_premium_info_button'}
                                                                    <button class="btn is--primary is--align-center is--icon-right" type="submit">
                                                                        {s name='PremiumActionAdd'}{/s}
                                                                        <i class="icon--arrow-right"></i>
                                                                    </button>
                                                                {/block}
                                                            {/if}
                                                        {/block}
                                                    </form>
                                                {/block}
                                            {else}
                                                <div class="btn premium-product--difference is--align-center is--disabled">
                                                    {s name="PremiumsInfoDifference"}{/s} <span class="difference--price">{$premium.sDifference|currency}</span>
                                                </div>
                                            {/if}
                                        </div>
                                    </div>
                                {/block}
                            {/foreach}
                        </div>
                    {/block}
                </div>
            {/block}
        </div>
    {/if}
{/block}
