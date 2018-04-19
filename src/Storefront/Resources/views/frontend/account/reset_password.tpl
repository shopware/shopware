{extends file='frontend/index/index.tpl'}

{* Main content *}
{block name='frontend_index_content'}
    <div class="content account--password-new">

        {* Error messages *}
        {block name="frontend_account_error_messages"}
            {if $sErrorMessages}
                <div class="account--error">
                    {include file="frontend/register/error_message.tpl" error_messages=$sErrorMessages}
                </div>
            {/if}
        {/block}

        {if !$invalidToken}

            {* New password panel *}
            {block name='frontend_account_password_new_content'}
                <div class="password-new--content panel has--border is--rounded">

                    {* New password panel title *}
                    {block name='frontend_account_password_new_title'}
                        <h2 class="password-new--title panel--title is--underline">
                            {s name='PasswordResetNewHeadline'}{/s}
                        </h2>
                    {/block}

                    {* New password form *}
                    {block name='frontend_account_password_new_form'}
                        <form action="{url action=resetPassword}" method="post" class="password-new--form">

                            {* New password panel content *}
                            {block name='frontend_account_password_new'}
                                <div class="panel--body is--wide is--align-center">

                                    {* New password fields *}
                                    {block name='frontend_account_password_new_fields'}
                                        <div class="password-new--fields">

                                            {* Secret hash hidden input *}
                                            {block name='frontend_account_password_new_hash_input'}
                                                <div class="password-new--hash">
                                                    <input name="hash"
                                                           value="{$hash}"
                                                           type="hidden"
                                                           id="hash"
                                                           class="password-new--input input--hash{if $sErrorFlag.hash} has--error{/if}">
                                                </div>
                                            {/block}

                                            {* New password input *}
                                            {block name='frontend_account_password_new_password_input'}
                                                <div class="password-new--password">
                                                    <input placeholder="{s name="AccountLabelNewPassword2" namespace='frontend/account/index'}{/s}{s name="Star" namespace="frontend/listing/box_article"}{/s}"
                                                           name="password[password]"
                                                           type="password"
                                                           autocomplete="new-password"
                                                           id="newpwd"
                                                           class="password-new--input input--password{if $sErrorFlag.password} has--error{/if}">
                                                </div>
                                            {/block}

                                            {* New password confirmation input *}
                                            {block name='frontend_account_password_new_password_confirmation_input'}
                                                <div class="password-new--password-confirmation">
                                                    <input placeholder="{s name="AccountLabelRepeatPassword2" namespace='frontend/account/index'}{/s}{s name="Star" namespace="frontend/listing/box_article"}{/s}"
                                                           name="password[passwordConfirmation]"
                                                           id="newpwdrepeat"
                                                           type="password"
                                                           autocomplete="new-password"
                                                           class="password-new--input input--password-confirmation{if $sErrorFlag.passwordConfirmation} has--error{/if}" >
                                                </div>
                                            {/block}
                                        </div>
                                    {/block}

                                    {* New password helptext *}
                                    {block name='frontend_account_password_new_helptext'}
                                        <p class="password-new--helptext">
                                            {s name='PasswordResetNewHelpText'}{/s}
                                        </p>
                                    {/block}
                                </div>
                            {/block}

                            {* New password actions *}
                            {block name='frontend_account_password_new_password_actions'}
                                <div class="password-new--actions panel--actions is--wide is--align-center">
                                    <button type="submit"
                                            name="AccountLinkChangePassword"
                                            class="btn password-new--submit is--primary is--icon-right">
                                        {s name='AccountLinkChangePassword' namespace='frontend/account/index'}{/s}
                                        <i class="icon--arrow-right"></i>
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
