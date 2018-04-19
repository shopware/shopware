{extends file="parent:frontend/listing/index.tpl"}

{namespace name="frontend/listing/listing"}

{block name='frontend_index_header_meta_tags_opengraph'}
    {$description = "{s name='IndexMetaDescriptionStandard'}{/s}"}
    {if $manufacturer->getDescription()}
        {$description = "{$manufacturer->getDescription()|strip_tags|trim|truncate:240}"}
    {/if}

    <meta property="og:type" content="product" />
    <meta property="og:site_name" content="{{config name=sShopname}|escapeHtml}" />
    <meta property="og:title" content="{$manufacturer->getName()|escapeHtml}" />
    <meta property="og:description" content="{$description|escapeHtml}" />

    <meta name="twitter:card" content="product" />
    <meta name="twitter:site" content="{{config name=sShopname}|escapeHtml}" />
    <meta name="twitter:title" content="{$manufacturer->getName()|escapeHtml}" />
    <meta name="twitter:description" content="{$description|escapeHtml}" />

    {* Images *}
    {if $manufacturer->getCoverFile()}
        <meta property="og:image" content="{$manufacturer->getCoverFile()}" />
        <meta name="twitter:image" content="{$manufacturer->getCoverFile()}" />
    {else}
        <meta property="og:image" content="{link file=$theme.desktopLogo fullPath}" />
        <meta name="twitter:image" content="{link file=$theme.desktopLogo fullPath}" />
    {/if}
{/block}

{block name="frontend_listing_listing_content"}
    <div class="listing"
         data-ajax-wishlist="true"
         data-compare-ajax="true"
            {if $theme.infiniteScrolling}
            data-infinite-scrolling="true"
            data-ajaxUrl="{url module="widgets" controller="Listing" action="ajaxListing" sSupplier=$manufacturer->getId()}"
            data-loadPreviousSnippet="{s name="ListingActionsLoadPrevious"}{/s}"
            data-loadMoreSnippet="{s name="ListingActionsLoadMore"}{/s}"
            data-categoryId="{$Shop->getCategory()->getId()}"
            data-pages="{$pages}"
            data-threshold="{$theme.infiniteThreshold}"{/if}>

        {* Actual listing *}
        {block name="frontend_listing_list_inline"}
            {foreach $sArticles as $sArticle}
                {include file="frontend/listing/box_article.tpl"}
            {/foreach}
        {/block}
    </div>
{/block}

{block name="frontend_listing_text"}
    <div class="vendor--info panel has--border">

        {* Vendor headline *}
        {block name="frontend_listing_list_filter_supplier_headline"}
            <h1 class="panel--title is--underline">
                {s name='ListingInfoFilterSupplier'}{/s} {$manufacturer->getName()|escapeHtml}
            </h1>
        {/block}

        {* Vendor content e.g. description and logo *}
        {block name="frontend_listing_list_filter_supplier_content"}
            <div class="panel--body is--wide">

                {if $manufacturer->getCoverFile()}
                    <div class="vendor--image-wrapper">
                        <img class="vendor--image" src="{$manufacturer->getCoverFile()}" alt="{$manufacturer->getName()|escape}">
                    </div>
                {/if}

                {if $manufacturer->getDescription()}
                    <div class="vendor--text">
                        {$manufacturer->getDescription()}
                    </div>
                {/if}
            </div>
        {/block}
    </div>
{/block}

{block name="frontend_listing_index_topseller"}
{/block}
