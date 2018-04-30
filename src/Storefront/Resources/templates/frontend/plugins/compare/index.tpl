{* Compare container *}
{block name='frontend_index_navigation_inline' append}
    {include file='frontend/compare/index.tpl'}
{/block}

{* Compare result *}
{block name='frontend_index_body_inline' append}
<div id="compare_bigbox"></div>
{/block}


{* Compare button *}
{block name='frontend_listing_box_article_actions_buy_now' prepend}
    <a href="{url controller='compare' action='add_article' articleID=$sArticle.articleID}"
       rel="nofollow"
       title="{"{s name='ListingBoxLinkCompare'}{/s}"|escape}"
       class="product--action action--compare btn is--secondary is--icon-right">
        {s name='ListingBoxLinkCompare'}{/s}
        <i class="icon--arrow-right"></i>
    </a>
{/block}

{* Compare button 2 *}
{block name='frontend_detail_actions_notepad' prepend}
    <a href="{url controller='compare' action='add_article' articleID=$sArticle.articleID}" rel="nofollow" title="{"{s name='DetailActionLinkCompare'}{/s}"|escape}" class="action--link action--compare">
        <i class="icon--compare"></i> {s name="DetailActionLinkCompare"}{/s}
    </a>
{/block}

{* Compare button note *}
{block name='frontend_note_item_actions_compare'}
    <a href="{url controller='compare' action='add_article' articleID=$sBasketItem.articleID}" class="product--action action--compare btn is--secondary" title="{"{s name='ListingBoxLinkCompare'}{/s}"|escape}" rel="nofollow">
        {s name='ListingBoxLinkCompare'}{/s}
    </a>
{/block}


