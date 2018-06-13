{extends file='frontend/index/index.tpl'}

{* Main content *}
{block name='frontend_index_content'}
    <div class="content account--password-reset">

        {* Error messages *}
        {block name='frontend_account_error_messages'}
            {if $sErrorMessages}
                <div class="account--error">
                    {include file="frontend/register/error_message.tpl" error_messages=$sErrorMessages}
                </div>
            {/if}
        {/block}

        {if $sSuccess}
            {* Success message *}
            {block name='frontend_account_password_success'}
                <div class="password--success">
                    {include file="frontend/_includes/messages.tpl" type="success" content="{s name='PasswordInfoSuccess'}{/s}"}
                </div>
                <a href="{url controller='account' action='password'}"
                   class="btn is--secondary is--icon-left">
                    <i class="icon--arrow-left"></i>{s name="LoginBack"}{/s}
                </a>
            {/block}
        {else}
            {* Recover password *}
            {block name="frontend_account_password_reset"}
                <div class="password-reset--content panel has--border is--rounded">

                    {block name="frontend_account_password_reset_headline"}
                        <h2 class="password-reset--title panel--title is--underline">{s name="PasswordHeader"}{/s}</h2>
                    {/block}

                    {block name='frontend_account_password_form'}
                        {* Recover password form *}
                        <form name="frmRegister" method="post" action="{url action=password}" class="password-reset--form">

                            {block name="frontend_account_password_reset_content"}
                                <div class="password-reset--form-content panel--body is--wide is--align-center">
                                    <p>
                                        <input name="email" type="email" required="required" aria-required="true" class="password-reset--input" placeholder="{s name='PasswordPlaceholderMail'}{/s}" />
                                    </p>
                                    <p>{s name="PasswordText"}{/s}</p>
                                </div>
                            {/block}

                            {* Recover password actions *}
                            {block name="frontend_account_password_reset_actions"}
                                <div class="password-reset--form-actions panel--actions is--wide is--align-center">
                                    <a href="{url controller='account'}"
                                       class="password-reset--link btn is--secondary is--icon-left is--center">
                                        <i class="icon--arrow-left"></i>{s name="PasswordLinkBack"}{/s}
                                    </a>
                                    <button type="submit"
                                            class="password-reset--link btn is--primary is--icon-right is--center">
                                        {s name="PasswordSendAction"}{/s} <i class="icon--arrow-right"></i>
                                    </button>
                                </div>
                            {/block}
                        </form>
                    {/block}
                </div>
            {/block}
        {/if}
    </div>
{/block}