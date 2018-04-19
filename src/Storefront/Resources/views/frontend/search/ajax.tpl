{if !$sSearchResults.sResults}

    {* no search results *}
    {block name="search_ajax_inner_no_results"}
        <ul class="results--list">
            <li class="list--entry entry--no-results result--item">{s name="SearchAjaxNoResults"}{/s}</li>
        </ul>
    {/block}

{else}

    {block name="search_ajax_inner"}
        <ul class="results--list">
            {foreach $sSearchResults.sResults as $search_result}

                {* Each product in the search result *}
                {block name="search_ajax_list_entry"}
                    <li class="list--entry block-group result--item">
                        <a class="search-result--link" href="{$search_result.link}" title="{$search_result.name|escape}">

                            {* Product image *}
                            {block name="search_ajax_list_entry_media"}
                                <span class="entry--media block">
                                    {if $search_result.image.thumbnails[0]}
                                        <img srcset="{$search_result.image.thumbnails[0].sourceSet}" alt="{$search_result.name|escape}" class="media--image">
                                    {else}
                                        <img src="{link file='frontend/_public/src/img/no-picture.jpg'}" alt="{"{s name='ListingBoxNoPicture'}{/s}"|escape}" class="media--image">
                                    {/if}
                                </span>
                            {/block}

                            {* Product name *}
                            {block name="search_ajax_list_entry_name"}
                                <span class="entry--name block">
                                    {$search_result.name|escapeHtml}
                                </span>
                            {/block}

                            {* Product price *}
                            {block name="search_ajax_list_entry_price"}
                                <span class="entry--price block">
                                    {$sArticle = $search_result}
                                    {*reset pseudo price value to prevent discount boxes*}
                                    {$sArticle.has_pseudoprice = 0}
                                    {include file="frontend/listing/product-box/product-price.tpl" sArticle=$sArticle}
                                </span>
                            {/block}
                        </a>
                    </li>
                {/block}
            {/foreach}

            {* Link to show all founded products using the built-in search *}
            {block name="search_ajax_all_results"}
                <li class="entry--all-results block-group result--item">

                    {* Link to the built-in search *}
                    {block name="search_ajax_all_results_link"}
                        <a href="{url controller="search"}?sSearch={$sSearchRequest.sSearch|urlencode}" class="search-result--link entry--all-results-link block">
                            <i class="icon--arrow-right"></i>
                            {s name="SearchAjaxLinkAllResults"}{/s}
                        </a>
                    {/block}

                    {* Result of all founded products *}
                    {block name="search_ajax_all_results_number"}
                        <span class="entry--all-results-number block">
                            {$sSearchResults.sArticlesCount} {s name='SearchAjaxInfoResults'}{/s}
                        </span>
                    {/block}
                </li>
            {/block}
        </ul>
    {/block}
{/if}
