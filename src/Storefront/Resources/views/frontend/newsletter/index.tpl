{extends file="frontend/index/index.tpl"}

{* Breadcrumb *}
{block name='frontend_index_start' append}
    {$sBreadcrumb = [['name'=>"{s name='NewsletterTitle'}{/s}", 'link'=>{url}]]}
{/block}

{* Meta description *}
{block name='frontend_index_header_meta_description'}{s name='NewsletterMetaDescriptionStandard'}{/s}{/block}

{* Meta opengraph tags *}
{block name='frontend_index_header_meta_tags_opengraph'}
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="{{config name=sShopname}|escapeHtml}" />
    <meta property="og:title" content="{{config name=sShopname}|escapeHtml}" />
    <meta property="og:description" content="{s name='NewsletterMetaDescriptionStandard'}{/s}" />
    <meta property="og:image" content="{link file=$theme.desktopLogo fullPath}" />

    <meta name="twitter:card" content="website" />
    <meta name="twitter:site" content="{{config name=sShopname}|escapeHtml}" />
    <meta name="twitter:title" content="{{config name=sShopname}|escapeHtml}" />
    <meta name="twitter:description" content="{s name='NewsletterMetaDescriptionStandard'}{/s}" />
    <meta name="twitter:image" content="{link file=$theme.desktopLogo fullPath}" />
{/block}

{block name="frontend_index_content"}
    <div class="newsletter--content content block">

        {* Error messages *}
        {block name="frontend_newsletter_error_messages"}
            {if $sStatus.code != 0}
                <div class="newsletter--error-messages">
                    {if $sStatus.code==3}
                        {include file="frontend/_includes/messages.tpl" type='success' content=$sStatus.message}
                    {elseif $sStatus.code==5}
                        {include file="frontend/_includes/messages.tpl" type='error' content=$sStatus.message}
                    {elseif $sStatus.code==2}
                        {include file="frontend/_includes/messages.tpl" type='warning' content=$sStatus.message}
                    {elseif $sStatus.code != 0}
                        {include file="frontend/_includes/messages.tpl" type='error' content=$sStatus.message}
                    {/if}
                </div>
            {/if}
        {/block}

        {* Newsletter headline *}
        {block name="frontend_newsletter_headline"}
            <div class="newsletter--headline panel--body is--wide has--border is--rounded">
                {block name="frontend_newsletter_headline_title"}
                    <h1 class="newsletter--title">{s name="NewsletterRegisterHeadline"}{/s}</h1>
                {/block}

                {block name="frontend_newsletter_headline_info"}
                    <p class="newsletter--info">{s name="sNewsletterInfo"}{/s}</p>
                {/block}
            </div>
        {/block}

        {* Newsletter content *}
        {block name="frontend_newsletter_content"}
            {if $voteConfirmed == false || $sStatus.code == 0}
            <div class="newsletter--form panel has--border is--rounded" data-newsletter="true">

                {* Newsletter headline *}
                {block name="frontend_newsletter_content_headline"}
                    <h2 class="panel--title is--underline">{s name="NewsletterRegisterHeadline"}{/s}</h2>
                {/block}

                {* Newsletter form *}
                {block name="frontend_newsletter_form"}
                    <form action="{url controller='newsletter'}" method="post">
                        <div class="panel--body is--wide">

                            {* Subscription option *}
                            {block name="frontend_newsletter_form_input_subscription"}
                                <div class="newsletter--subscription select-field">
                                    <select name="subscribeToNewsletter" required="required" class="field--select newsletter--checkmail">
                                        <option value="1">{s name="sNewsletterOptionSubscribe"}{/s}</option>
                                        <option value="-1"{if $_POST.subscribeToNewsletter eq -1 || (!$_POST.subscribeToNewsletter && $sUnsubscribe == true)} selected="selected"{/if}>{s name="sNewsletterOptionUnsubscribe"}{/s}</option>
                                    </select>
                                </div>
                            {/block}

                            {* Email *}
                            {block name="frontend_newsletter_form_input_email"}
                                <div class="newsletter--email">
                                    <input name="newsletter" type="email" placeholder="{s name="sNewsletterPlaceholderMail"}{/s}{s name="RequiredField" namespace="frontend/register/index"}{/s}" required="required" aria-required="true" value="{if $_POST.newsletter}{$_POST.newsletter}{elseif $_GET.sNewsletter}{$_GET.sNewsletter|escape}{/if}" class="input--field is--required{if $sStatus.sErrorFlag.newsletter} has--error{/if}"/>
                                </div>
                            {/block}

                            {* Additonal fields *}
                            {block name="frontend_newsletter_form_additionalfields"}
                                {if {config name=NewsletterExtendedFields}}
                                    <div class="newsletter--additional-form">

                                        {getSalutations variable="salutations"}

                                        {* Salutation *}
                                        {block name="frontend_newsletter_form_input_salutation"}
                                            <div class="newsletter--salutation select-field">
                                                <select name="salutation" class="field--select">
                                                    <option value=""{if $_POST.salutation eq ""} selected="selected"{/if}>{s name='NewsletterRegisterPlaceholderSalutation'}{/s}</option>
                                                    {foreach $salutations as $key => $label}
                                                        <option value="{$key}"{if $_POST.salutation eq $key} selected="selected"{/if}>{$label}</option>
                                                    {/foreach}
                                                </select>
                                            </div>
                                        {/block}

                                        {* Firstname *}
                                        {block name="frontend_newsletter_form_input_firstname"}
                                            <div class="newsletter--firstname">
                                                <input name="firstname" type="text" placeholder="{s name="NewsletterRegisterPlaceholderFirstname"}{/s}" value="{$_POST.firstname|escape}" class="input--field{if $sStatus.sErrorFlag.firstname} has--error{/if}"/>
                                            </div>
                                        {/block}

                                        {* Lastname *}
                                        {block name="frontend_newsletter_form_input_lastname"}
                                            <div class="newsletter--lastname">
                                                <input name="lastname" type="text" placeholder="{s name="NewsletterRegisterPlaceholderLastname"}{/s}" value="{$_POST.lastname|escape}" class="input--field{if $sStatus.sErrorFlag.lastname} has--error{/if}"/>
                                            </div>
                                        {/block}

                                        {* Street *}
                                        {block name="frontend_newsletter_form_input_street"}
                                            <div class="newsletter--street">
                                                <input name="street" type="text" placeholder="{s name="NewsletterRegisterBillingPlaceholderStreet"}{/s}" value="{$_POST.street|escape}" class="input--field input--field-street{if $sStatus.sErrorFlag.street} has--error{/if}"/>
                                            </div>
                                        {/block}

                                        {* Zip + City *}
                                        {block name="frontend_newsletter_form_input_zip_and_city"}
                                            <div class="newsletter--zip-city">
                                                {if {config name=showZipBeforeCity}}
                                                    <input name="zipcode" type="text" placeholder="{s name="NewsletterRegisterBillingPlaceholderZipcode"}{/s}" value="{$_POST.zipcode|escape}" class="input--field input--field-zipcode input--spacer{if $sStatus.sErrorFlag.zipcode} has--error{/if}"/>
                                                    <input name="city" type="text" placeholder="{s name="NewsletterRegisterBillingPlaceholderCityname"}{/s}" value="{$_POST.city|escape}" size="25" class="input--field input--field-city{if $sStatus.sErrorFlag.city} has--error{/if}"/>
                                                {else}
                                                    <input name="city" type="text" placeholder="{s name="NewsletterRegisterBillingPlaceholderCityname"}{/s}" value="{$_POST.city|escape}" size="25" class="input--field input--field-city input--spacer{if $sStatus.sErrorFlag.city} has--error{/if}"/>
                                                    <input name="zipcode" type="text" placeholder="{s name="NewsletterRegisterBillingPlaceholderZipcode"}{/s}" value="{$_POST.zipcode|escape}" class="input--field input--field-zipcode{if $sStatus.sErrorFlag.zipcode} has--error{/if}"/>
                                                {/if}
                                            </div>
                                        {/block}

                                    </div>

                                {/if}
                            {/block}

                            {* Required fields hint *}
                            {block name="frontend_newsletter_form_required"}
                                <div class="newsletter--required-info">
                                    {s name='RegisterPersonalRequiredText' namespace="frontend/register/personal_fieldset"}{/s}
                                </div>
                            {/block}

                            {* Submit button *}
                            {block name="frontend_newsletter_form_submit"}
                                <div class="newsletter--action">
                                    <button type="submit" class="btn is--primary right is--icon-right" name="{s name="sNewsletterButton"}{/s}">
                                        {s name="sNewsletterButton"}{/s}
                                        <i class="icon--arrow-right"></i>
                                    </button>
                                </div>
                            {/block}
                        </div>
                    </form>
                {/block}
            </div>
            {/if}
        {/block}
    </div>
{/block}
