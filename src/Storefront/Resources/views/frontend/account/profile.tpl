{extends file='frontend/account/index.tpl'}

{* Breadcrumb *}
{block name='frontend_index_start' append}
    {$sBreadcrumb[] = ['name'=>"{s name="ProfileHeadline"}{/s}", 'link'=>{url}]}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div class="account--profile">

        {block name="frontend_account_profile_profile_form"}
            <form name="profileForm" action="{url controller=account action=saveProfile}" method="post">

                {block name="frontend_account_profile_profile_panel"}
                    <div class="panel has--border is--rounded">

                        {block name="frontend_account_profile_profile_title"}
                            <div class="panel--title is--underline">{s name="ProfileHeadline"}{/s}</div>
                        {/block}

                        {block name="frontend_account_profile_profile_body"}
                            <div class="panel--body is--wide">

                                {block name="frontend_account_profile_profile_success"}
                                    {if $section == 'profile' && $success}
                                        {include file="frontend/_includes/messages.tpl" type="success" content="{s name="ProfileSaveSuccessMessage"}{/s}"}
                                    {/if}
                                {/block}

                                {* Error messages *}
                                {block name="frontend_account_profile_profile_errors"}
                                    {if $section == 'profile' && $errorMessages}
                                        {include file="frontend/register/error_message.tpl" error_messages=["{s name="ErrorFillIn" namespace="frontend/account/internalMessages"}{/s}"]}
                                    {/if}
                                {/block}

                                {* Salutation *}
                                {block name='frontend_account_profile_profile_input_salutation'}
                                    {getSalutations variable="salutations"}

                                    <div class="profile--salutation field--select select-field">
                                        <select name="profile[salutation]"
                                                required="required"
                                                aria-required="true"
                                                class="is--required{if $errorFlags.salutation} has--error{/if}">

                                            <option value="" disabled="disabled"{if $form_data.profile.salutation eq ""} selected="selected"{/if}>{s name='RegisterPlaceholderSalutation' namespace="frontend/register/personal_fieldset"}{/s}{s name="RequiredField" namespace="frontend/register/index"}{/s}</option>

                                            {foreach $salutations as $key => $label}
                                                <option value="{$key}"{if $form_data.profile.salutation eq $key} selected="selected"{/if}>{$label}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                {/block}

                                {* Title *}
                                {block name='frontend_account_profile_profile_input_title'}
                                    {if {config name="displayprofiletitle"}}
                                        <div class="profile--title">
                                            <input autocomplete="section-personal title"
                                                   name="profile[title]"
                                                   type="text"
                                                   placeholder="{s name='RegisterPlaceholderTitle' namespace="frontend/register/personal_fieldset"}{/s}"
                                                   value="{$form_data.profile.title|escape}"
                                                   class="profile--field{if $errorFlags.title} has--error{/if}" />
                                        </div>
                                    {/if}
                                {/block}

                                {* Firstname *}
                                {block name='frontend_account_profile_profile_input_firstname'}
                                    <div class="profile--firstname">
                                        <input autocomplete="section-personal given-name"
                                               name="profile[firstname]"
                                               type="text"
                                               required="required"
                                               aria-required="true"
                                               placeholder="{s name='RegisterPlaceholderFirstname' namespace="frontend/register/personal_fieldset"}{/s}{s name="RequiredField" namespace="frontend/register/index"}{/s}"
                                               value="{$form_data.profile.firstname|escape}" class="profile--field is--required{if $errorFlags.firstname} has--error{/if}"
                                        />
                                    </div>
                                {/block}

                                {* Lastname *}
                                {block name="frontend_account_profile_profile_input_lastname"}
                                    <div class="profile--lastname">
                                        <input autocomplete="section-personal family-name"
                                               name="profile[lastname]"
                                               type="text"
                                               required="required"
                                               aria-required="true"
                                               placeholder="{s name='RegisterPlaceholderLastname' namespace="frontend/register/personal_fieldset"}{/s}{s name="RequiredField" namespace="frontend/register/index"}{/s}"
                                               value="{$form_data.profile.lastname|escape}"
                                               class="profile--field is--required{if $errorFlags.lastname} has--error{/if}"
                                        />
                                    </div>
                                {/block}

                                {* Birthday *}
                                {block name="frontend_account_profile_profile_input_birthday"}
                                    {if {config name=showBirthdayField}}
                                        <div class="profile--birthdate">
                                            {block name="frontend_account_profile_profile_input_birthday_label"}
                                                <strong class="birthday--label">{s name='RegisterPlaceholderBirthday' namespace="frontend/register/personal_fieldset"}{/s}{if {config name=requireBirthdayField}}{s name="RequiredField" namespace="frontend/register/index"}{/s}{/if}</strong>
                                            {/block}

                                            {block name="frontend_account_profile_profile_input_birthday_day"}
                                                <div class="profile--birthday field--select select-field">
                                                    <select name="profile[birthday][day]"
                                                            {if {config name=requireBirthdayField}} required="required" aria-required="true"{/if}
                                                            class="{if {config name=requireBirthdayField}}is--required{/if}{if $errorFlags.birthday && {config name=requireBirthdayField}} has--error{/if}">

                                                        <option{if {config name=requireBirthdayField} && $form_data.profile.birthday.day} disabled="disabled"{/if} value="">{s name='RegisterBirthdaySelectDay' namespace="frontend/register/personal_fieldset"}{/s}</option>

                                                        {for $day = 1 to 31}
                                                            <option value="{$day}" {if $day == $form_data.profile.birthday.day}selected{/if}>{$day}</option>
                                                        {/for}
                                                    </select>
                                                </div>
                                            {/block}

                                            {block name="frontend_account_profile_profile_input_birthday_month"}
                                                <div class="profile--birthmonth field--select select-field">
                                                    <select name="profile[birthday][month]"
                                                            {if {config name=requireBirthdayField}} required="required" aria-required="true"{/if}
                                                            class="{if {config name=requireBirthdayField}}is--required{/if}{if $errorFlags.birthday && {config name=requireBirthdayField}} has--error{/if}">

                                                        <option{if {config name=requireBirthdayField} && $form_data.profile.birthday.month} disabled="disabled"{/if} value="">{s name='RegisterBirthdaySelectMonth' namespace="frontend/register/personal_fieldset"}{/s}</option>

                                                        {for $month = 1 to 12}
                                                            <option value="{$month}" {if $month == $form_data.profile.birthday.month}selected{/if}>{$month}</option>
                                                        {/for}
                                                    </select>
                                                </div>
                                            {/block}

                                            {block name="frontend_account_profile_profile_input_birthday_year"}
                                                <div class="profile--birthyear field--select select-field">
                                                    <select name="profile[birthday][year]"
                                                            {if {config name=requireBirthdayField}} required="required" aria-required="true"{/if}
                                                            class="{if {config name=requireBirthdayField}}is--required{/if}{if $errorFlags.birthday && {config name=requireBirthdayField}} has--error{/if}">

                                                        <option{if {config name=requireBirthdayField} && $form_data.profile.birthday.year} disabled="disabled"{/if} value="">{s name='RegisterBirthdaySelectYear' namespace="frontend/register/personal_fieldset"}{/s}</option>

                                                        {for $year = date("Y") to date("Y")-120 step=-1}
                                                            <option value="{$year}" {if $year == $form_data.profile.birthday.year}selected{/if}>{$year}</option>
                                                        {/for}
                                                    </select>
                                                </div>
                                            {/block}
                                        </div>
                                    {/if}
                                {/block}

                                {block name="frontend_account_profile_profile_required_info"}
                                    <div class="required-info required_fields">
                                        {s name='RegisterPersonalRequiredText' namespace='frontend/register/personal_fieldset'}{/s}
                                    </div>
                                {/block}

                            </div>
                        {/block}

                        {block name="frontend_account_profile_profile_actions"}
                            <div class="panel--actions is--wide">
                                {block name="frontend_account_profile_profile_actions_submit"}
                                    <button class="btn is--block is--primary" type="submit" data-preloader-button="true">
                                        {s name="ProfileSaveButton"}{/s}
                                    </button>
                                {/block}
                            </div>
                        {/block}
                    </div>
                {/block}
            </form>
        {/block}

        <div class="profile--email-password-container" data-panel-auto-resizer="true">
            <div class="profile-email--container">
                {block name="frontend_account_profile_email_form"}
                    <form name="emailForm" action="{url controller=account action=saveEmail}" method="post">

                        {block name="frontend_account_profile_email_panel"}
                            <div class="panel has--border is--rounded">

                                {block name="frontend_account_profile_email_title"}
                                    <div class="panel--title is--underline">{s name="EmailHeadline"}{/s}</div>
                                {/block}

                                {block name="frontend_account_profile_email_body"}
                                    <div class="panel--body is--wide">

                                        {block name="frontend_account_profile_email_success"}
                                            {if $section == 'email' && $success}
                                                {include file="frontend/_includes/messages.tpl" type="success" content="{s name="EmailSaveSuccessMessage"}{/s}"}
                                            {/if}
                                        {/block}

                                        {* Error messages *}
                                        {block name="frontend_account_profile_email_errors"}
                                            {if $section == 'email'}
                                                {include file="frontend/register/error_message.tpl" error_messages=$errorMessages}
                                            {/if}
                                        {/block}

                                        {block name="frontend_account_profile_email_current"}
                                            <div>
                                                <strong>{s name="EmailCurrentEmailLabel"}{/s}</strong>
                                                <div class="profile--field">
                                                    {$sUserData.additional.user.email}
                                                </div>
                                            </div>
                                        {/block}

                                        {* Email *}
                                        {block name='frontend_account_profile_email_input_email'}
                                            <div class="profile--email">
                                                <input autocomplete="section-personal email"
                                                       required="required"
                                                       aria-required="true"
                                                       name="email[email]"
                                                       type="email"
                                                       value="{$form_data.email.email}"
                                                       placeholder="{s name="AccountLabelNewMail" namespace="frontend/account/index"}{/s}{s name="Star" namespace="frontend/listing/box_article"}{/s}"
                                                       class="profile--field is--required {if $errorFlags.email}has--error{/if}" />
                                            </div>
                                        {/block}

                                        {* Email confirmation *}
                                        {block name='frontend_account_profile_email_input_email_confirmation'}
                                            <div class="profile--email-confirmation">
                                                <input name="email[emailConfirmation]"
                                                       type="email"
                                                       required="required"
                                                       aria-required="true"
                                                       value="{$form_data.email.emailConfirmation}"
                                                       placeholder="{s name="AccountLabelMail" namespace="frontend/account/index"}{/s}{s name="Star" namespace="frontend/listing/box_article"}{/s}"
                                                       class="profile--field is--required {if $errorFlags.emailConfirmation}has--error{/if}"
                                                />
                                            </div>
                                        {/block}

                                        {block name='frontend_account_profile_email_input_current_password'}
                                            {if {config name=accountPasswordCheck}}
                                                <div class="profile--current-password">
                                                    <input name="email[currentPassword]"
                                                           type="password"
                                                           autocomplete="current-password"
                                                           required="required"
                                                           aria-required="true"
                                                           placeholder="{s name="AccountLabelCurrentPassword2" namespace="frontend/account/index"}{/s}{s name="Star" namespace="frontend/listing/box_article"}{/s}"
                                                           class="profile--field is--required {if $section == 'email' && $errorFlags.currentPassword}has--error{/if}"
                                                    />
                                                </div>
                                            {/if}
                                        {/block}

                                        {block name="frontend_account_profile_email_required_info"}
                                            <div class="required-info required_fields">
                                                {s name='RegisterPersonalRequiredText' namespace='frontend/register/personal_fieldset'}{/s}
                                            </div>
                                        {/block}
                                    </div>
                                {/block}

                                {block name="frontend_account_profile_email_actions"}
                                    <div class="panel--actions is--wide">
                                        {block name="frontend_account_profile_email_actions_submit"}
                                            <button class="btn is--block is--primary" type="submit" data-preloader-button="true">
                                                {s name="EmailSaveButton"}{/s}
                                            </button>
                                        {/block}
                                    </div>
                                {/block}
                            </div>
                        {/block}
                    </form>
                {/block}
            </div>

            <div class="profile-password--container">
                {block name="frontend_account_profile_password_form"}
                    <form name="passwordForm" action="{url controller=account action=savePassword}" method="post">

                        {block name="frontend_account_profile_password_panel"}
                            <div class="panel has--border is--rounded">

                                {block name="frontend_account_profile_password_title"}
                                    <div class="panel--title is--underline">{s name="PasswordHeadline"}{/s}</div>
                                {/block}

                                {block name="frontend_account_profile_password_body"}
                                    <div class="panel--body is--wide">

                                        {block name="frontend_account_profile_password_success"}
                                            {if $section == 'password' && $success}
                                                {include file="frontend/_includes/messages.tpl" type="success" content="{s name="PasswordSaveSuccessMessage"}{/s}"}
                                            {/if}
                                        {/block}

                                        {* Error messages *}
                                        {block name="frontend_account_profile_password_errors"}
                                            {if $section == 'password'}
                                                {include file="frontend/register/error_message.tpl" error_messages=$errorMessages}
                                            {/if}
                                        {/block}

                                        {* Password *}
                                        {block name='frontend_account_profile_password_input_password'}
                                            <div class="profile--password">
                                                <input name="password[password]"
                                                       type="password"
                                                       autocomplete="new-password"
                                                       required="required"
                                                       aria-required="true"
                                                       placeholder="{s name="AccountLabelNewPassword2" namespace="frontend/account/index"}{/s}{s name="Star" namespace="frontend/listing/box_article"}{/s}"
                                                       class="profile--field is--required {if $errorFlags.password}has--error{/if}"
                                                />
                                            </div>
                                        {/block}

                                        {* Password confirmation *}
                                        {block name='frontend_account_profile_password_input_password_confirmation'}
                                            <div class="profile--password-confirmation">
                                                <input name="password[passwordConfirmation]"
                                                       type="password"
                                                       autocomplete="new-password"
                                                       required="required"
                                                       aria-required="true"
                                                       placeholder="{s name="AccountLabelRepeatPassword2" namespace="frontend/account/index"}{/s}{s name="Star" namespace="frontend/listing/box_article"}{/s}"
                                                       class="profile--field is--required {if $errorFlags.passwordConfirmation}has--error{/if}"
                                                />
                                            </div>
                                        {/block}

                                        {block name='frontend_account_profile_password_input_current_password'}
                                            {if {config name=accountPasswordCheck}}
                                                <div class="profile--current-password">
                                                    <input name="password[currentPassword]"
                                                           type="password"
                                                           autocomplete="current-password"
                                                           required="required"
                                                           aria-required="true"
                                                           placeholder="{s name="AccountLabelCurrentPassword2" namespace="frontend/account/index"}{/s}{s name="Star" namespace="frontend/listing/box_article"}{/s}"
                                                           class="profile--field is--required {if $section == 'password' && $errorFlags.currentPassword}has--error{/if}"
                                                    />
                                                </div>
                                            {/if}
                                        {/block}

                                        {block name="frontend_account_profile_password_required_info"}
                                            <div class="required-info required_fields">
                                                {s name='RegisterPersonalRequiredText' namespace='frontend/register/personal_fieldset'}{/s}
                                            </div>
                                        {/block}
                                    </div>
                                {/block}

                                {block name="frontend_account_profile_password_actions"}
                                    <div class="panel--actions is--wide">
                                        {block name="frontend_account_profile_password_actions_submit"}
                                            <button class="btn is--block is--primary" type="submit" data-preloader-button="true">
                                                {s name="PasswordSaveButton"}{/s}
                                            </button>
                                        {/block}
                                    </div>
                                {/block}
                            </div>
                        {/block}
                    </form>
                {/block}
            </div>
        </div>
    </div>
{/block}
