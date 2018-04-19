{namespace name="frontend/detail/comment"}

{* Offcanvas buttons *}
{block name='frontend_detail_rating_buttons_offcanvas'}
    <div class="buttons--off-canvas">
        {block name='frontend_detail_rating_buttons_offcanvas_inner'}
            <a href="#" title="{"{s name="OffcanvasCloseMenu" namespace="frontend/detail/description"}{/s}"|escape}" class="close--off-canvas">
                <i class="icon--arrow-left"></i>
                {s name="OffcanvasCloseMenu" namespace="frontend/detail/description"}{/s}
            </a>
        {/block}
    </div>
{/block}

<div class="content--product-reviews" id="detail--product-reviews">

    {* Response save comment *}
    {if $sAction == "ratingAction"}
        {block name='frontend_detail_comment_error_messages'}
            {if $sErrorFlag}
                {if $sErrorFlag['sCaptcha']}
                    {include file="frontend/_includes/messages.tpl" type="error" content="{s name='DetailCommentInfoFillOutCaptcha'}{/s}"}
                {else}
                    {include file="frontend/_includes/messages.tpl" type="error" content="{s name='DetailCommentInfoFillOutFields'}{/s}"}
                {/if}
            {else}
                {if {config name="OptinVote"} && !{$smarty.get.sConfirmation} && !{$userLoggedIn}}
                    {include file="frontend/_includes/messages.tpl" type="success" content="{s name='DetailCommentInfoSuccessOptin'}{/s}"}
                {else}
                    {include file="frontend/_includes/messages.tpl" type="success" content="{s name='DetailCommentInfoSuccess'}{/s}"}
                {/if}
            {/if}
        {/block}
    {/if}

    {* Review title *}
    {block name="frontend_detail_tabs_rating_title"}
        <div class="content--title">
            {s name="DetailCommentHeader"}{/s} "{$sArticle.articleName}"
        </div>
    {/block}

    {* Display review *}
    {if $sArticle.sVoteComments}
        {foreach $sArticle.sVoteComments as $vote}

            {* Review entry *}
            {block name="frontend_detail_comment_block"}
                {include file="frontend/detail/comment/entry.tpl" isLast=$vote@last}
            {/block}

            {* Review answer *}
            {block name="frontend_detail_answer_block"}
                {if $vote.answer}
                    {include file="frontend/detail/comment/answer.tpl" isLast=$vote@last}
                {/if}
            {/block}
        {/foreach}
    {/if}

    {* Publish product review *}
    {block name='frontend_detail_comment_post'}
        <div class="review--form-container">
            {include file="frontend/detail/comment/form.tpl"}
        </div>
    {/block}
</div>
