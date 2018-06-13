{extends file='frontend/index/index.tpl'}

{block name='frontend_index_header'}
    {include file='frontend/blog/header.tpl'}
{/block}

{* Main content *}
{block name='frontend_index_content'}
    <div class="blog--content block-group">

        {* Blog Sidebar *}
        {block name='frontend_blog_listing_sidebar'}
            {include file='frontend/blog/listing_sidebar.tpl'}
        {/block}

        {* Blog Banner *}
        {block name='frontend_blog_index_banner'}
            {include file="frontend/listing/banner.tpl"}
        {/block}

        {* Blog listing *}
        {block name='frontend_blog_index_listing'}
            {include file="frontend/blog/listing.tpl"}
        {/block}
    </div>
{/block}