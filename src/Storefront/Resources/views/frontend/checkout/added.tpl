{block name='frontend_checkout_added_info_teaser'}
    {if $sArticleName}

        <div class="alert is--success is--rounded">
            {* Icon column *}
            <div class="alert--icon">
                <i class="icon--element icon--check"></i>
            </div>

            {* Content column *}
            <div class="alert--content is--strong">
                {s name="CheckoutAddArticleInfoAdded"}{/s}
            </div>
        </div>
    {/if}
{/block}
