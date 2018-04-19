{extends file="frontend/index/index.tpl"}

{* Left sidebar *}
{block name="frontend_index_content_left"}
    {include file='frontend/index/sidebar.tpl'}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div class="content tellafriend--content right">

        {if $sSuccess}
            {include file="frontend/_includes/messages.tpl" type="success" content="{s name='TellAFriendHeaderSuccess'}{/s}"}
        {else}
            {if $sError}
                {include file="frontend/_includes/messages.tpl" type="error" content="{s name='TellAFriendInfoFields'}{/s}"}
            {/if}
        {/if}

        {block name='frontend_tellafriend_success'}
            {if !$sSuccess}

                {block name='frontend_tellafriend_form'}
                    <form name="mailtofriend" class="panel tellafriend--form has--border is--rounded" method="post">
                    <input type="hidden" name="sMailTo" value="1"/>
                    <input type="hidden" name="sDetails" value="{$sArticle.articleID}"/>

                    {* Validation errors *}
                    {if $error}
                        {include file="frontend/_includes/messages.tpl" type="error" list=$error}
                    {/if}

                    {block name='frontend_tellafriend_headline'}
                        <h2 class="panel--title is--underline">
                            <a href="{$sArticle.linkDetails}" title="{$sArticle.articleName|escape}">{$sArticle.articleName}</a> {s name='TellAFriendHeadline'}{/s}
                        </h2>
                    {/block}

                    <div class="panel--body is--wide">

                        {* TellAFriend name *}
                        {block name='frontend_tellafriend_field_name'}
                            <div class="tellafriend--name">
                                <input name="sName" type="text" class="tellafriend--field is--required" required="required" aria-required="true" placeholder="{s name='TellAFriendLabelName'}{/s}{s name="RequiredField" namespace="frontend/register/index"}{/s}" value="{$sName|escape}"/>
                            </div>
                        {/block}

                        {* TellAFriend email address *}
                        {block name='frontend_tellafriend_field_email'}
                            <div class="tellafriend--email">
                                <input name="sMail" type="email" class="tellafriend--field is--required" required="required" aria-required="true" placeholder="{s name='TellAFriendPlaceholderMail'}{/s}{s name="RequiredField" namespace="frontend/register/index"}{/s}" value="{$sMail|escape}"/>
                            </div>
                        {/block}

                        {* TellAFriend receiver email address *}
                        {block name='frontend_tellafriend_field_friendsemail'}
                            <div class="tellafriend--receiver-email">
                                <input name="sRecipient" type="email" class="tellafriend--field is--required" required="required" aria-required="true" placeholder="{s name='TellAFriendLabelFriendsMail'}{/s}{s name="RequiredField" namespace="frontend/register/index"}{/s}" value="{$sRecipient|escape}"/>
                            </div>
                        {/block}

                        {* TellAFriend comment *}
                        {block name='frontend_tellafriend_field_comment'}
                            <div class="tellafriend--comment">
                                <textarea name="sComment" class="tellafriend--field" placeholder="{s name='TellAFriendPlaceholderComment'}{/s}">{$sComment|escape}</textarea>
                            </div>
                        {/block}

                        {* Captcha *}
                        {block name='frontend_tellafriend_captcha'}
                            <div class="tellafriend--captcha">
                                {if {config name=captchaMethod} === 'legacy'}
                                    {* Deferred loading of the captcha image *}
                                    {block name='frontend_tellafriend_captcha_placeholder'}
                                        <div class="captcha--placeholder"{if $sErrorFlag.sCaptcha} data-hasError="true"{/if} data-src="{url module=widgets controller=Captcha action=refreshCaptcha}"></div>
                                    {/block}

                                    {block name='frontend_tellafriend_captcha_label'}
                                        <strong class="captcha--notice">{s name="TellAFriendLabelCaptchaInfo"}{/s}</strong>
                                    {/block}

                                    {block name='frontend_tellafriend_captcha_field_code'}
                                        <div class="captcha--code">
                                            <input type="text" name="sCaptcha" required="required" aria-required="true" class="tellafriend--field is--required{if $sErrorFlag.sCaptcha} has--error{/if}"/>
                                        </div>
                                    {/block}
                                {else}
                                    <div class="captcha--placeholder" data-src="{url module=widgets controller=Captcha action=index}"{if $sError} data-hasError="true"{/if}></div>
                                {/if}
                            </div>
                        {/block}

                    {* Notice that all fields which contains a star symbole needs to be filled out *}
                    {block name='frontend_tellafriend_captcha_notice'}
                        <p class="review--notice">
                            {s name="TellAFriendMarkedInfoFields"}{/s}
                        </p>
                    {/block}

                    {* Send recommendation button *}
                    {block name='frontend_tellafriend_captcha_code_actions'}
                        <div class="tellafriend--buttons">
                            <a href="{$sArticle.linkDetails}" class="btn is--secondary left is--icon-left"><i class="icon--arrow-left"></i>{s name='TellAFriendLinkBack'}{/s}</a>

                            <button type="submit" class="btn is--primary right is--icon-right">
                                {s name='TellAFriendActionSubmit'}{/s}<i class="icon--arrow-right"></i>
                            </button>
                        </div>
                    {/block}
                    </div>
                </form>
                {/block}

            {/if}
        {/block}
    </div>
{/block}

{* Empty right sidebar *}
{block name='frontend_index_content_right'}{/block}
