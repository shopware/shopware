{namespace name="frontend/detail/comment"}

{* Review title *}
{block name='frontend_detail_comment_post_title'}
    <div id="product--publish-comment" class="content--title">
        {s name="DetailCommentHeaderWriteReview"}{/s}
    </div>
{/block}

{* Display notice if the shop owner needs to unlock a comment before it will be listed *}
{block name='frontend_detail_comment_post_notice'}
    {if {config name=VoteUnlock}}
        {include file="frontend/_includes/messages.tpl" type="warning" content="{s name='DetailCommentTextReview'}{/s}"}
    {/if}
{/block}

{* Publish review form *}
{block name='frontend_detail_comment_post_form'}
    <form method="post" action="{url action='rating' sArticle=$sArticle.articleID sCategory=$sArticle.categoryID}#detail--product-reviews" class="content--form review--form">

        {* Review author name *}
        {block name='frontend_detail_comment_input_name'}
            <input name="sVoteName" type="text" value="{$sFormData.sVoteName|escape}" class="review--field{if $sErrorFlag.sVoteName} has--error{/if}" placeholder="{s name="DetailCommentLabelName"}{/s}*" required="required" aria-required="true" />
        {/block}

        {* Reviewer email address *}
        {block name='frontend_detail_comment_input_mail'}
            {if {config name=OptinVote} == true}
                <input name="sVoteMail" type="email" value="{$sFormData.sVoteMail|escape}" class="review--field{if $sErrorFlag.sVoteMail} has--error{/if}" placeholder="{s name="DetailCommentLabelMail"}{/s}*" required="required" aria-required="true" />
            {/if}
        {/block}

        {* Review summary *}
        {block name='frontend_detail_comment_input_summary'}
            <input name="sVoteSummary" type="text" value="{$sFormData.sVoteSummary|escape}" id="sVoteSummary" class="review--field{if $sErrorFlag.sVoteSummary} has--error{/if}" placeholder="{s name="DetailCommentLabelSummary"}{/s}*" required="required" aria-required="true" />
        {/block}

        {* Review Rating *}
        {block name='frontend_detail_comment_input_rating'}
            <div class="field--select review--field select-field">
                <select name="sVoteStars">
                    <option value="10">{s name="Rate10"}{/s}</option>
                    <option value="9">{s name="Rate9"}{/s}</option>
                    <option value="8">{s name="Rate8"}{/s}</option>
                    <option value="7">{s name="Rate7"}{/s}</option>
                    <option value="6">{s name="Rate6"}{/s}</option>
                    <option value="5">{s name="Rate5"}{/s}</option>
                    <option value="4">{s name="Rate4"}{/s}</option>
                    <option value="3">{s name="Rate3"}{/s}</option>
                    <option value="2">{s name="Rate2"}{/s}</option>
                    <option value="1">{s name="Rate1"}{/s}</option>
                </select>
            </div>
        {/block}

        {* Review text *}
        {block name='frontend_detail_comment_input_text'}
            <textarea name="sVoteComment" placeholder="{s name="DetailCommentPlaceholderText"}{/s}" cols="3" rows="2" class="review--field{if $sErrorFlag.sVoteComment} has--error{/if}">{$sFormData.sVoteComment|escape}</textarea>
        {/block}

        {* Captcha *}
        {block name='frontend_detail_comment_input_captcha'}
            {if {config name=captchaMethod} === 'legacy'}
                <div class="review--captcha">

                    {* Deferred loading of the captcha image *}
                    {block name='frontend_detail_comment_input_captcha_placeholder'}
                        <div class="captcha--placeholder" {if $sErrorFlag.sCaptcha} data-hasError="true"{/if} data-src="{url module=widgets controller=Captcha action=refreshCaptcha}"></div>
                    {/block}

                    {block name='frontend_detail_comment_input_captcha_label'}
                        <strong class="captcha--notice">{s name="DetailCommentLabelCaptcha"}{/s}</strong>
                    {/block}

                    {block name='frontend_detail_comment_input_captcha_code'}
                        <div class="captcha--code">
                            <input type="text" name="sCaptcha" class="review--field{if $sErrorFlag.sCaptcha} has--error{/if}" required="required" aria-required="true" />
                        </div>
                    {/block}
                </div>
            {else}
                <div class="captcha--placeholder" data-src="{url module=widgets controller=Captcha action=index}"{if isset($sErrorFlag) && count($sErrorFlag) > 0} data-hasError="true"{/if}></div>
            {/if}
        {/block}

        {* Notice that all fields which contains a star symbole needs to be filled out *}
        {block name='frontend_detail_comment_input_notice'}
            <p class="review--notice">
                {s name="DetailCommentInfoFields"}{/s}
            </p>
        {/block}

        {* Review actions *}
        {block name='frontend_detail_comment_input_actions'}
            <div class="review--actions">

                {* Publish review button *}
                {block name='frontend_detail_comment_input_actions_submit'}
                    <button type="submit" class="btn is--primary" name="Submit">
                        {s name="DetailCommentActionSave"}{/s}
                    </button>
                {/block}
            </div>
        {/block}
    </form>
{/block}
