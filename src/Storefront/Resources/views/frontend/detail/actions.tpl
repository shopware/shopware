{block name='frontend_detail_actions_compare'}
    {if {config name="compareShow"}}
        <form action="{url controller='compare' action='add_article' articleID=$sArticle.articleID}" method="post" class="action--form">
            <button type="submit" data-product-compare-add="true" title="{"{s name='DetailActionLinkCompare'}{/s}"|escape}" class="action--link action--compare">
                <i class="icon--compare"></i> {s name="DetailActionLinkCompare"}{/s}
            </button>
        </form>
    {/if}
{/block}

{block name='frontend_detail_actions_notepad'}
    <form action="{url controller='note' action='add' ordernumber=$sArticle.ordernumber}" method="post" class="action--form">
        <button type="submit"
           class="action--link link--notepad"
           title="{"{s name='DetailLinkNotepad'}{/s}"|escape}"
           data-ajaxUrl="{url controller='note' action='ajaxAdd' ordernumber=$sArticle.ordernumber}"
           data-text="{s name="DetailNotepadMarked"}{/s}">
            <i class="icon--heart"></i> <span class="action--text">{s name="DetailLinkNotepadShort"}{/s}</span>
        </button>
    </form>
{/block}

{block name='frontend_detail_actions_review'}
    {if !{config name=VoteDisable}}
        <a href="#content--product-reviews" data-show-tab="true" class="action--link link--publish-comment" rel="nofollow" title="{"{s name='DetailLinkReview'}{/s}"|escape}">
            <i class="icon--star"></i> {s name="DetailLinkReviewShort"}{/s}
        </a>
    {/if}
{/block}

{block name='frontend_detail_actions_voucher'}
    {if {config name=showTellAFriend}}
        <a href="{$sArticle.linkTellAFriend}" rel="nofollow" title="{"{s name='DetailLinkVoucher'}{/s}"|escape}" class="action--link link--tell-a-friend">
            <i class="icon--comment"></i> {s name="DetailLinkVoucherShort"}{/s}
        </a>
    {/if}
{/block}
