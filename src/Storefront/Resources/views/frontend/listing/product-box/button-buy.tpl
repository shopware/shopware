{block name="frontend_listing_product_box_button_buy"}

    {block name="frontend_listing_product_box_button_buy_url"}
        {$url = {url controller=checkout action=addProduct sTargetAction=ajaxAddArticle}}
    {/block}

    {block name="frontend_listing_product_box_button_buy_form"}
        <form name="sAddToBasket"
              method="post"
              action="{$url}"
              class="buybox--form"
              data-add-article="true"
              data-eventName="submit"
              {if $theme.offcanvasCart}
                 data-showModal="false"
                 data-addArticleUrl="{url controller=checkout action=addProduct sTargetAction=ajaxCart}"
              {/if}>

            {block name="frontend_listing_product_box_button_buy_order_number"}
                <input type="hidden" name="number" value="{$sArticle.ordernumber}"/>
                <input type="hidden" name="quantity" value="1"/>
            {/block}

            {block name="frontend_listing_product_box_button_buy_button"}
                <button class="buybox--button block btn is--primary is--icon-right is--center is--large">
                    {block name="frontend_listing_product_box_button_buy_button_text"}
                        {s namespace="frontend/listing/box_article" name="ListingBuyActionAdd"}{/s}<i class="icon--basket"></i> <i class="icon--arrow-right"></i>
                    {/block}
                </button>
            {/block}
        </form>
    {/block}
{/block}
