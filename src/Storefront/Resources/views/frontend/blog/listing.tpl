<div class="blog--listing block">
    {if $sCategoryContent.cmsheadline || $sCategoryContent.cmstext}
        {include file="frontend/listing/text.tpl"}
    {/if}

    {* Blog Filter Button *}
    {block name='frontend_blog_listing_filter_button'}
        <div class="blog--filter-btn">
            <a href="#"
               title="{"{s namespace='frontend/listing/listing_actions' name='ListingFilterButton'}{/s}"|escape}"
               class="filter--trigger btn is--icon-left"
               data-collapseTarget=".blog--filter-options"
               data-offcanvas="true"
               data-offCanvasSelector=".blog--filter-options"
               data-closeButtonSelector=".blog--filter-close-btn">
                <i class="icon--filter"></i> {s namespace='frontend/listing/listing_actions' name='ListingFilterButton'}{/s}
            </a>
        </div>
    {/block}

    {if $sBlogArticles}
        {foreach from=$sBlogArticles item=sArticle key=key name="counter"}
            {include file="frontend/blog/box.tpl" sArticle=$sArticle key=$key}
        {/foreach}

        {* Paging *}
        {block name="frontend_listing_bottom_paging"}
            {if $sNumberPages > 1}
                {include file='frontend/blog/listing_actions.tpl'}
            {/if}
        {/block}
    {/if}
</div>

