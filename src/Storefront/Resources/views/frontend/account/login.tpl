{extends file='frontend/index/index.tpl'}

{* Breadcrumb *}
{block name='frontend_index_start' append}
    {$sBreadcrumb = [['name'=>"{s name='AccountLoginTitle'}{/s}", 'link'=>{url}]]}
{/block}

{* Main content *}
{block name='frontend_index_content'}

    {* Error messages *}
    {block name='frontend_account_login_error_messages'}
        {include file="frontend/register/error_message.tpl" error_messages=$sErrorMessages}
    {/block}

<div id="login">

    {* New customer *}
    {block name='frontend_account_login_new'}
    <div class="grid_10">
        <h2 class="headingbox_dark largesize">{s name="LoginHeaderNew"}{/s} {$sShopname|escapeHtml}</h2>
        <div class="inner_container">
            <p>{s name="LoginInfoNew"}{/s}</p>
            <form method="post" name="new_customer" class="new_customer_form" action="{url controller='register'}">
                {if !{config name=NoAccountDisable}}
                    <div class="checkbox">
                        <p>
                            <input type="checkbox" class="chk_noaccount" name="skipLogin" value="1" />
                            <strong>{s name="LoginLabelNoAccount"}{/s}</strong>
                        </p>
                    </div>
                {/if}
                <input type="submit" class="button-right large register_now" value="{s name='LoginLinkRegister'}{/s}" />
            </form>
        </div>
    </div>
    {/block}

    {* Existing customer *}
    {block name='frontend_account_login_customer'}
    <div class="grid_10">
        <h2 class="headingbox_dark largesize">{s name="LoginHeaderExistingCustomer"}{/s}</h2>
        <div class="inner_container">
            <form name="sLogin" method="post" action="{url action=login}">
                {if $sTarget}<input name="sTarget" type="hidden" value="{$sTarget|escape}" />{/if}
                <fieldset>
                    <p>{s name="LoginHeaderFields"}{/s}</p>
                    <p>
                        <label for="email">{s name='LoginLabelMail'}{/s}</label>
                        <input name="email" type="email" autocomplete="email" tabindex="1" value="{$sFormData.email|escape}" id="email" class="text {if $sErrorFlag.email}instyle_error{/if}" />
                    </p>
                    <p class="none">
                        <label for="passwort">{s name="LoginLabelPassword"}{/s}</label>
                        <input name="password" type="password" autocomplete="current-password" tabindex="2" id="passwort" class="text {if $sErrorFlag.password}instyle_error{/if}" />
                    </p>
                </fieldset>

                <p class="password">
                    <a href="{url action=password}" title="{"{s name='LoginLinkLostPassword'}{/s}"|escape}">
                        {s name="LoginLinkLostPassword"}{/s}
                    </a>
                </p>
                <div class="action">
                    <input class="button-middle small" type="submit" value="{s name='LoginLinkLogon'}{/s}" name="Submit"/>
                </div>
            </form>
        </div>
    </div>
    {/block}
</div>
{/block}

{* Empty sidebar left *}
{block name='frontend_index_content_left'}{/block}

{* Empty sidebar right *}
{block name='frontend_index_content_right'}{/block}
