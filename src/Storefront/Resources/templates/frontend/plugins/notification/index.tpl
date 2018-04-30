<div class="product--notification">
    <input type="hidden" value="{$NotifyHideBasket}" name="notifyHideBasket" id="notifyHideBasket" />

    {if $NotifyValid == true}
        {$messageType="success"}
        {$messageContent="{s name='DetailNotifyInfoValid'}{/s}"}
    {elseif $NotifyInvalid == true && $NotifyAlreadyRegistered != true}
        {$messageType="warning"}
        {$messageContent="{s name='DetailNotifyInfoInvalid'}{/s}"}
    {elseif $NotifyEmailError == true}
        {$messageType="error"}
        {$messageContent="{s name='DetailNotifyInfoErrorMail'}{/s}"}
    {elseif $WaitingForOptInApprovement}
        {$messageType="success"}
        {$messageContent="{s name='DetailNotifyInfoSuccess'}{/s}"}
    {elseif $NotifyAlreadyRegistered == true}
        {$messageType="warning"}
        {$messageContent="{s name='DetailNotifyAlreadyRegistered'}{/s}"}
    {else}
        {if $NotifyValid != true}
            {$messageType="warning"}
            {$messageContent="{s name='DetailNotifyHeader'}{/s}"}
        {/if}
    {/if}

    {* Include the message template component *}
    {include file="frontend/_includes/messages.tpl" type=$messageType content=$messageContent}

    {block name="frontend_detail_index_notification_form"}
        {if !$NotifyAlreadyRegistered}
            <form method="post" action="{url action='notify' sArticle=$sArticle.articleID}" class="notification--form block-group">
                <input type="hidden" name="notifyOrdernumber" value="{$sArticle.ordernumber}" />
                {block name="frontend_detail_index_notification_field"}
                    <input name="sNotificationEmail" type="email" class="notification--field block" placeholder="{s name='DetailNotifyLabelMail'}{/s}" />
                {/block}

                {block name="frontend_detail_index_notification_button"}
                    <button type="submit" class="notification--button btn is--center block">
                        <i class="icon--mail"></i>
                    </button>
                {/block}
            </form>
        {/if}
    {/block}
</div>