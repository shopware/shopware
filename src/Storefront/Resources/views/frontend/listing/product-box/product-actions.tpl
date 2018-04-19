{namespace name="frontend/listing/box_article"}

{* Product actions *}
{block name='frontend_listing_box_article_actions_content'}
    <div class="product--actions">

        {* Compare button *}
        {block name='frontend_listing_box_article_actions_compare'}
            {if {config name="compareShow"}}
                <form action="{url controller='compare' action='add_article' articleID=$sArticle.articleID _seo=false}" method="post">
                    <button type="submit"
                       title="{s name='ListingBoxLinkCompare'}{/s}"
                       class="product--action action--compare"
                       data-product-compare-add="true">
                        <i class="icon--compare"></i> {s name='ListingBoxLinkCompare'}{/s}
                    </button>
                </form>
            {/if}
        {/block}

        {* Note button *}
        {block name='frontend_listing_box_article_actions_save'}
            <form action="{url controller='note' action='add' ordernumber=$sArticle.ordernumber _seo=false}" method="post">
                <button type="submit"
                   title="{"{s name='DetailLinkNotepad' namespace='frontend/detail/actions'}{/s}"|escape}"
                   class="product--action action--note"
                   data-ajaxUrl="{url controller='note' action='ajaxAdd' ordernumber=$sArticle.ordernumber _seo=false}"
                   data-text="{s name="DetailNotepadMarked"}{/s}">
                    <i class="icon--heart"></i> <span class="action--text">{s name="DetailLinkNotepadShort" namespace="frontend/detail/actions"}{/s}</span>
                </button>
            </form>
        {/block}

        {* @deprecated: block no longer in use *}
        {block name='frontend_listing_box_article_actions_more'}{/block}

        {* @deprecated: misleading name *}
        {block name="frontend_listing_box_article_actions_inline"}{/block}
    </div>
{/block}
