{extends file='frontend/index/index.tpl'}

{block name='frontend_index_header'}
    {include file='frontend/listing/header.tpl'}
{/block}

{block name='frontend_index_content_left'}

    {block name='frontend_index_controller_url'}
        {* Controller url for the found products counter *}
        {$countCtrlUrl = "{url module="widgets" controller="listing" action="listingCount" params=$ajaxCountUrlParams fullPath}"}
    {/block}

    {include file='frontend/listing/sidebar.tpl'}
{/block}

{* Main content *}
{block name='frontend_index_content'}
    <div class="content listing--content">

        {* Banner *}
        {block name="frontend_listing_index_banner"}
            {if !$hasEmotion}
                {include file='frontend/listing/banner.tpl'}
            {/if}
        {/block}

        {* Category headline *}
        {block name="frontend_listing_index_text"}
            {if !$hasEmotion}
                {include file='frontend/listing/text.tpl'}
            {/if}
        {/block}

        {* Topseller *}
        {block name="frontend_listing_index_topseller"}
            {if !$hasEmotion && {config name=topSellerActive}}
                {action module=widgets controller=listing action=top_seller sCategory=$sCategoryContent.id}
            {/if}
        {/block}

        {* Define all necessary template variables for the listing *}
        {block name="frontend_listing_index_layout_variables"}

            {$emotionViewports = [0 => 'xl', 1 => 'l', 2 => 'm', 3 => 's', 4 => 'xs']}

            {* Count of available product pages *}
            {$pages = 1}

            {if $criteria}
                {$pages = ceil($sNumberArticles / $criteria->getLimit())}
            {/if}

            {* Layout for the product boxes *}
            {$productBoxLayout = 'basic'}

            {if $sCategoryContent.productBoxLayout !== null &&
                $sCategoryContent.productBoxLayout !== 'extend'}
                {$productBoxLayout = $sCategoryContent.productBoxLayout}
            {/if}
        {/block}

        {* Listing *}
        {block name="frontend_listing_index_listing"}
            {include file='frontend/listing/listing.tpl'}
        {/block}
    </div>
{/block}

{* Sidebar right *}
{block name='frontend_index_content_right'}{/block}
