{* Blog listing sidebar right *}
{block name='frontend_index_content_right'}
    <div class="blog--filter-options off-canvas">

        {* Filter container *}
        {block name='frontend_listing_actions_filter_container'}

            {block name='frontend_listing_actions_filter_closebtn'}
                <a href="#" title="{"{s name="ListingActionsCloseFilter"}{/s}"|escape}" class="blog--filter-close-btn">{s namespace='frontend/listing/listing_actions' name='ListingActionsCloseFilter'}{/s} <i class="icon--arrow-right"></i></a>
            {/block}

            <div class="filter--container">

                {* Filter headline *}
                {block name="frontend_listing_actions_filter_container_inner"}
                    <div class="filter--headline">{s name='FilterHeadline'}{/s}</div>
                {/block}

                <div class="blog--sidebar">

                    {* Blog navigation *}
                    {block name="frontend_blog_index_navigation"}
                        <div class="blog--navigation block-group">

                            {* Subscribe Atom + RSS *}
                            {block name='frontend_blog_index_subscribe'}
                                <div class="blog--subscribe has--border is--rounded filter--group block">

                                    {* Subscribe headline *}
                                    {block name="frontend_blog_index_subscribe_headline"}
                                        <div class="blog--subscribe-headline blog--sidebar-title collapse--header blog-filter--trigger">
                                            {s name="BlogSubscribe"}{/s}<span class="filter--expand-collapse collapse--toggler"></span>
                                        </div>
                                    {/block}

                                    {* Subscribe Content *}
                                    {block name="frontend_blog_index_subscribe_content"}
                                        <div class="blog--subscribe-content blog--sidebar-body collapse--content">
                                            <ul class="filter--list list--unstyled">
                                                {block name="frontend_blog_index_subscribe_entry_rss"}
                                                    <li class="filter--entry"><a class="filter--entry-link" href="{$sCategoryContent.rssFeed}" title="{$sCategoryContent.description|escape}">{s namespace="frontend/blog/index" name="BlogLinkRSS"}{/s}</a></li>
                                                {/block}

                                                {block name="frontend_blog_index_subscribe_entry_atom"}
                                                    <li class="filter--entry is--last"><a class="filter--entry-link" href="{$sCategoryContent.atomFeed}" title="{$sCategoryContent.description|escape}">{s namespace="frontend/blog/index" name="BlogLinkAtom"}{/s}</a></li>
                                                {/block}
                                            </ul>
                                        </div>
                                    {/block}
                                </div>
                            {/block}

                            {* Blog filter *}
                            {block name='frontend_blog_index_filter'}
                                {include file="frontend/blog/filter.tpl"}
                            {/block}
                        </div>
                    {/block}

                </div>
            </div>

        {/block}
    </div>
{/block}
