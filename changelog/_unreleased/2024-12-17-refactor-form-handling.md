---
title: Refactor form handling for better accessibility
issue: NEXT-39235
---
# Storefront
* Added new helper service `form-validation.helper.js` for implementing local form validation best practices. An instance is available under `window.formValidation`.
* Added new plugin `form-handler.plugin.js` that implements local form validation handling to a form. It replaces three other plugins: `form-validation.plugin`, `form-scroll-to-invalid-field.plugin`, and `form-submit-loader.plugin`. The plugin is currently only used when feature flag `ACCESSIBILITY_TWEAKS` is activated.
* Added new form field templates in Twig, that can be used like a component to implement all accessibility best-practices, which also work perfectly together with the new `form-handler.plugin`.
  * Added `storefront/component/form/form-input.html.twig`.
  * Added `storefront/component/form/form-select.html.twig`.
  * Added `storefront/component/form/form-select-birthday.html.twig`.
  * Added `storefront/component/form/form-checkbox.html.twig`.
  * Added `storefront/component/form/form-textarea.html.twig`.
* Changed all forms in the Storefront to implement the new `form-handler.plugin` and the new field components. These changes are only available with the `ACCESSIBILITY_TWEAKS` flag enabled.
  * Changed `component/account/login.html.twig`.
  * Changed `component/account/register.html.twig`.
  * Changed `component/address/address-form.html.twig`.
  * Changed `component/address/address-personal.html.twig`.
  * Changed `component/address/address-personal-company.html.twig`.
  * Changed `component/address/address-personal-vat-id.html.twig`.
  * Changed `component/address/address-editor-modal-create-address.html.twig`.
  * Changed `component/review/review-form.html.twig`.
  * Changed `component/privacy-notice.html.twig`.
  * Changed `element/cms-element-form/form-components/cms-element-form-input.html.twig`.
  * Changed `element/cms-element-form/form-components/cms-element-form-select-salutation.html.twig`.
  * Changed `element/cms-element-form/form-components/cms-element-form-textarea.html.twig`.
  * Changed `element/cms-element-form/form-components/cms-element-form-privacy.html.twig`.
  * Changed `element/cms-element-form/form-types/contact-form.html.twig`.
  * Changed `element/cms-element-form/form-types/newsletter-form.html.twig`.
  * Changed `page/account/addressbook/create.html.twig`.
  * Changed `page/account/addressbook/edit.html.twig`.
  * Changed `page/account/profile/index.html.twig`.
  * Changed `page/account/profile/recover-password.html.twig`.
  * Changed `page/account/profile/reset-password.html.twig`.
  * Changed `component/captcha/basicCaptcha.html.twig`.
  * Changed `component/captcha/googleReCaptchaV2.html.twig`.
  * Changed `component/captcha/googleReCaptchaV3.html.twig`.
* Changed `plugin/captcha/basic-captcha.plugin.js` to work in association with the new form handling.
* Changed several snippets of form violation messages to optimize their content.
  * Changed `error.VIOLATION::INVALID_EMAIL_FORMAT_ERROR` snippet.
  * Changed `error.VIOLATION::NOT_EQUAL_ERROR` snippet.
  * Changed `error.VIOLATION::FIRST_NAME_IS_BLANK_ERROR` snippet.
  * Changed `error.VIOLATION::LAST_NAME_IS_BLANK_ERROR` snippet.
  * Changed `error.VIOLATION::STREET_IS_BLANK_ERROR` snippet.
  * Changed `error.VIOLATION::CITY_IS_BLANK_ERROR` snippet.
  * Changed `error.VIOLATION::COUNTRY_IS_BLANK_ERROR` snippet.
  * Changed `error.VIOLATION::ADDITIONAL_ADDR1_IS_BLANK_ERROR` snippet.
  * Changed `error.VIOLATION::ADDITIONAL_ADDR2_IS_BLANK_ERROR` snippet.
  * Changed `error.VIOLATION::PHONE_NUMBER_IS_BLANK_ERROR` snippet.
  * Changed `address.companyVatLabel` snippet.
* Changed `form-country-state-select.plugin.js` to be compatible with the new `form-handler.plugin`. The flag `ACCESSIBILITY_TWEAKS` has to be active for this.
* Changed `$enable-validation-icons` variable in SCSS to use the additional icons to show the state of a form field. Only used when flag `ACCESSIBILITY_TWEAKS` is active.
* Deprecated `page/account/profile/personal.html.twig` for v6.7.0 - Use `address-personal.html.twig` instead.
* Deprecated `component/captcha/basicCaptchaFields.html.twig` for v6.7.0 - The fields are already implemented in `basicCaptcha.html.twig`.
* Deprecated several Twig blocks for v6.7.0 because of the replacement by the central form field components:
  * Deprecated block `component_account_login_form_mail_label` in `login.html.twig`.
  * Deprecated block `component_account_login_form_mail_input` in `login.html.twig`.
  * Deprecated block `component_account_login_form_password_label` in `login.html.twig`.
  * Deprecated block `component_account_login_form_password_input` in `login.html.twig`.
  * Deprecated block `component_account_register_personal_mail_label` in `register.html.twig`.
  * Deprecated block `component_account_register_personal_mail_input` in `register.html.twig`.
  * Deprecated block `component_account_register_personal_mail_input_error` in `register.html.twig`.
  * Deprecated block `component_account_register_personal_mail_confirmation_label` in `register.html.twig`.
  * Deprecated block `component_account_register_personal_mail_confirmation_input` in `register.html.twig`.
  * Deprecated block `component_account_register_personal_mail_confirmation_input_error` in `register.html.twig`.
  * Deprecated block `component_account_register_personal_password_label` in `register.html.twig`.
  * Deprecated block `component_account_register_personal_password_input` in `register.html.twig`.
  * Deprecated block `component_account_register_personal_password_description` in `register.html.twig`.
  * Deprecated block `component_account_register_personal_password_input_error` in `register.html.twig`.
  * Deprecated block `component_account_register_personal_password_confirmation_label` in `register.html.twig`.
  * Deprecated block `component_account_register_personal_password_confirmation_input` in `register.html.twig`.
  * Deprecated block `component_account_register_personal_password_confirmation_input_error` in `register.html.twig`.
  * Deprecated block `component_address_form_company_name_label` in `address-form.html.twig`.
  * Deprecated block `component_address_form_company_name_input` in `address-form.html.twig`.
  * Deprecated block `component_address_form_company_name_input_error` in `address-form.html.twig`.
  * Deprecated block `component_address_form_company_department_label` in `address-form.html.twig`.
  * Deprecated block `component_address_form_company_department_input` in `address-form.html.twig`.
  * Deprecated block `component_address_form_company_department_input_error` in `address-form.html.twig`.
  * Deprecated block `component_address_form_street_label` in `address-form.html.twig`.
  * Deprecated block `component_address_form_street_input` in `address-form.html.twig`.
  * Deprecated block `component_address_form_street_input_error` in `address-form.html.twig`.
  * Deprecated block `component_address_form_zipcode_label` in `address-form.html.twig`.
  * Deprecated block `component_address_form_zipcode_input` in `address-form.html.twig`.
  * Deprecated block `component_address_form_zipcode_error` in `address-form.html.twig`.
  * Deprecated block `component_address_form_city_label` in `address-form.html.twig`.
  * Deprecated block `component_address_form_city_input` in `address-form.html.twig`.
  * Deprecated block `component_address_form_city_error` in `address-form.html.twig`.
  * Deprecated block `component_address_form_additional_field1_label` in `address-form.html.twig`.
  * Deprecated block `component_address_form_additional_field1_input` in `address-form.html.twig`.
  * Deprecated block `component_address_form_additional_field1_error` in `address-form.html.twig`.
  * Deprecated block `component_address_form_additional_field2_label` in `address-form.html.twig`.
  * Deprecated block `component_address_form_additional_field2_input` in `address-form.html.twig`.
  * Deprecated block `component_address_form_additional_field2_error` in `address-form.html.twig`.
  * Deprecated block `component_address_form_country_label` in `address-form.html.twig`.
  * Deprecated block `component_address_form_country_select` in `address-form.html.twig`.
  * Deprecated block `component_address_form_country_violation_error` in `address-form.html.twig`.
  * Deprecated block `component_address_form_country_state_label` in `address-form.html.twig`.
  * Deprecated block `component_address_form_country_state_select` in `address-form.html.twig`.
  * Deprecated block `component_address_form_country_state_error` in `address-form.html.twig`.
  * Deprecated block `component_address_form_country_error` in `address-form.html.twig`.
  * Deprecated block `component_address_form_phone_number_label` in `address-form.html.twig`.
  * Deprecated block `component_address_form_phone_number_input` in `address-form.html.twig`.
  * Deprecated block `component_address_form_phone_error` in `address-form.html.twig`.
  * Deprecated block `component_address_personal_account_type_label` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_account_type_select` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_account_type_error` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_salutation_label` in `address-personal.html.twig`.
  * Deprecated block `component_address_form_salutation_select` in `address-personal.html.twig`.
  * Deprecated block `component_address_form_salutation_select_error` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_title_label` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_title_input` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_title_input_error` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_first_name_label` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_first_name_input` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_first_name_input_error` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_last_name_label` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_last_name_input` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_last_name_input_error` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_company_name_label` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_company_name_input` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_company_name_input_error` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_birthday_label` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_birthday_selects` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_birthday_select_day` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_birthday_select_day_error` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_birthday_select_month` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_birthday_select_month_error` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_birthday_select_year` in `address-personal.html.twig`.
  * Deprecated block `component_address_personal_fields_birthday_select_year_error` in `address-personal.html.twig`.
  * Deprecated block `component_address_form_company_name_label` in `address-personal-company.html.twig`.
  * Deprecated block `component_address_form_company_name_input` in `address-personal-company.html.twig`.
  * Deprecated block `component_address_form_company_name_input_error` in `address-personal-company.html.twig`.
  * Deprecated block `component_address_form_company_department_label` in `address-personal-company.html.twig`.
  * Deprecated block `component_address_form_company_department_input` in `address-personal-company.html.twig`.
  * Deprecated block `component_address_form_company_department_input_error` in `address-personal-company.html.twig`.
  * Deprecated block `component_address_form_company_vatId_label` in `address-personal-vat-id.html.twig`.
  * Deprecated block `component_address_form_company_vatId_input` in `address-personal-vat-id.html.twig`.
  * Deprecated block `component_address_form_company_vatId_input_error` in `address-personal-vat-id.html.twig`.
  * Deprecated block `component_review_form_title_label` in `review-form.html.twig`.
  * Deprecated block `component_review_form_title_input` in `review-form.html.twig`.
  * Deprecated block `component_review_form_title_violation` in `review-form.html.twig`.
  * Deprecated block `component_review_form_content_label` in `review-form.html.twig`.
  * Deprecated block `component_review_form_content_textarea` in `review-form.html.twig`.
  * Deprecated block `component_review_form_content_violation` in `review-form.html.twig`.
  * Deprecated block `component_privacy_dpi_checkbox` in `privacy-notice.html.twig`.
  * Deprecated block `component_privacy_dpi_label` in `privacy-notice.html.twig`.
  * Deprecated block `cms_element_form_input_label` in `cms-element-form-input.html.twig`.
  * Deprecated block `cms_element_form_input_input` in `cms-element-form-input.html.twig`.
  * Deprecated block `cms_form_select_salutation_content_label` in `cms-element-form-select-salutation.html.twig`.
  * Deprecated block `cms_form_select_salutation_content_select` in `cms-element-form-select-salutation.html.twig`.
  * Deprecated block `cms_element_form_textarea_label` in `cms-element-form-textarea.html.twig`.
  * Deprecated block `cms_element_form_textarea_textarea` in `cms-element-form-textarea.html.twig`.
  * Deprecated block `cms_form_privacy_opt_in_input` in `cms-element-form-privacy.html.twig`.
  * Deprecated block `cms_form_privacy_opt_in_label` in `cms-element-form-privacy.html.twig`.
  * Deprecated block `page_account_profile_mail_label` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_mail_input` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_mail_input_error` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_personal_mail_confirmation_label` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_mail_confirmation_input` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_mail_confirmation_input_error` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_mail_password_label` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_mail_password_input` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_mail_password_input_error` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_new_password_label` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_new_password_input` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_new_password_input_error` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_new_password_confirmation_label` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_new_password_confirmation_input` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_new_password_confirmation_error` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_current_password_label` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_current_password_input` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_current_password_input_error` in `account/profile/index.html.twig`.
  * Deprecated block `page_account_profile_recover_password_mail_label` in `recover-password.html.twig`.
  * Deprecated block `page_account_profile_recover_password_mail_input` in `recover-password.html.twig`.
  * Deprecated block `page_account_profile_recover_password_mail_input_violations` in `recover-password.html.twig`.
  * Deprecated block `page_account_profile_reset_password_new_label` in `reset-password.html.twig`.
  * Deprecated block `page_account_profile_reset_password_new_input` in `reset-password.html.twig`.
  * Deprecated block `page_account_profile_reset_password_new_violations` in `reset-password.html.twig`.
  * Deprecated block `page_account_profile_reset_password_confirm_label` in `reset-password.html.twig`.
  * Deprecated block `page_account_profile_reset_password_confirm_input` in `reset-password.html.twig`.
  * Deprecated block `page_account_profile_reset_password_confirm_violations` in `reset-password.html.twig`.
  * Deprecated block `page_account_guest_auth_mail_label` in `guest-auth.html.twig`.
  * Deprecated block `page_account_guest_auth_mail_input` in `guest-auth.html.twig`.
  * Deprecated block `page_account_guest_auth_postcode` in `guest-auth.html.twig`. Use `page_account_guest_auth_zipcode` instead.
  * Deprecated block `page_account_guest_auth_postcode_label` in `guest-auth.html.twig`.
  * Deprecated block `page_account_guest_auth_postcode_input` in `guest-auth.html.twig`.
* Deprecated `form-validation.plugin.js` for v.6.8.0 - Use `form-handler.plugin.js` instead.
* Deprecated `form-scroll-to-invalid-field.plugin.js` for v.6.8.0 - Use `form-handler.plugin.js` instead.
* Deprecated `form-submit-loader.plugin.js` for v.6.8.0 - Use `form-handler.plugin.js` instead.

___
# Upgrade Information

## Improved local form handling in the Storefront
To make forms more accessible, we overhauled the form handling in the Storefront, which includes local form validation and best-practices for user feedback.

### Implemented best-practices
Sadly, the native browser validation methods are not accessible by default. Therefore, we decided to create custom form handling and disable the native validation of the browser. In the following you can learn more about specific best-practices we implemented for accessible form handling and optimization for screen readers.

#### Form
* The form has the `novalidate` attribute to not use native validation by the browser.
* The `checkValidity()` method of the form is replaced by a custom implementation.

#### Required fields
* The asterisk to mark required fields has now a highlight color for better contrast.
* The asterisk got the `aria-hidden` attribute, because it is irritating to screen readers. The required state is already read out by screen readers if the form field has the necessary attributes.
* Required fields are not marked with a `required` attribute, but with a `aria-required` attribute. This marks the field as required from a semantic standpoint and will be read out by screen readers, but will not trigger native validation.

#### Input validation
* Fields are validated by a local form validation on direct change, but only if changed. The `input` event is used instead of the `change` or `blur` event because these fire everytime and not on immediate change. It is a common pattern that keyboard users tab through a form to get a sense of available fields without filling them out. Therefore, the fields should only be validated on change and then with immediate feedback. If the feedback happens only on `blur` event, it can be irritating because the user has already moved on to the next field.
* Field validation can be configured via the `data-validation` attribute. You can pass a comma separated list of validation rules the field should be checked against. You can define the priority of these validators by their order. The first validator has the highest priority. This is important to always give the user the right validation feedback. Only the validation message with the highest applying validator is shown to the user and read out by screen readers.
* By default, the validators `required`, `email`, `confirmation`, and `minLength` are available for local form validation. You can extend these and add your own if needed.

#### Input feedback
* Every field has a feedback area beneath it that is referenced with the `aria-describedby` attribute. It is used to show a validation message to users, which is also read out by screen readers.
* Optionally every input field can have a description area beneath it to give more context to the user. If present, the description is also referenced via the `aria-describedby` attribute and read out by screen readers.
* Besides the normal color feedback, invalid fields now also display an error icon on the right side of the input. This is an important visual feedback for users which find it difficult to identify colors.
* Placeholder labels were removed from most form fields, as they are irritating and don't add value, especially if they just mirror the content of the label.

#### Form validation
* Besides the immediate field validation feedback, all fields will be validated when submitting the form. 
* If there are still invalid fields, they will be highlighted with the necessary visual feedback.
* In addition, the page will focus the first invalid field. The browser will automatically scroll the page to that field if it is not in view.

### Implementation

#### New validation service and form handler plugin
There is a new central form validation class that is also available as a default instance under `window.formValidation`. This is used by a new form handler plugin that will automatically implement the necessary events and handling on a form element. It can be activated with the `data-form-handler="true"` attribute on a form element.

**Example:**  
```HTML
<form action="/newsletter" method="post" data-form-handler="true">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" data-validation="required,email">
    

    <button type="submit">Submit</button>
</form>
```
The form validation works with an associated `data-validation` attribute on the form fields. You can pass a comma separated list of validator keys. Their priority is defined by their order. Only the validation message of the highest applying validator is shown to the user for relevant feedback.

These validators are available by default:  

| Key      | Description |
| -------- | ------- |
| `required` | Checks if the field is not empty. |
| `email` | Checks the value of the field to be a valid email address. |
| `conformation` | Checks if the value of a confirmation field matches the value of the original field. Make sure to use the right ID naming for the validator to work. As an example, the orginal field has the ID `email` and the confirmation field has the ID `emailConfirmation`. The `confirmation` validator should be added to the confirmation field. Note that unnecessary inputs are seen as not accessible and should be avoided wherever possible. |
| `minLength` | Checks the value of the field for a minimum length. If available the validator will use the `minlength` attribute of the field to validate against. Otherwise, it will use the default configuration of eight characters. | 

You can add your own custom validators via the global `formValidation` class.

```JavaScript
window.formValidation.addValidator('custom', (value, field) => {
    // You custom validation. Should return a boolean.
}, 'Your custom validation message.');
```

You can take a look at the reference documentation of the service and the plugin for further information.

#### New form field components in Twig
To make it easier to implement all best practices without recreating a lot of boilerplate code for every form field, we created new templates for different field types which can be used for easy form field rendering. You can find them in `views/storefront/components/form/`. These components work in association with the described local form handling but also the additional server-side validation.

**Example usage:**  
```TWIG
<form action="/newsletter" method="post" data-form-handler="true">

    {% sw_include '@Storefront/storefront/component/form/form-input.html.twig' with {
        type: 'email',
        label: 'account.personalMailLabel'|trans|sw_sanitize,
        id: 'personalMail',
        name: 'email',
        value: data.get('email'),
        autocomplete: 'section-personal email',
        violationPath: '/email',
        validationRules: 'required,email',
        additionalClass: 'col-sm-6',
    } %}

    <button type="submit">Submit</button>
</form>
```

### Updated Shopware standard forms
The existing forms in the Shopware Storefront are already reworked to use the described practices and tools. The changes are part of our accessibility initiative and are still behind a feature flag. They will become the default with the Shopware 6.7.0.0 major version. If you already want to get these changes among other accessibility improvements you can activate the flag `ACCESSIBILITY_TWEAKS`.

**Forms that are updated:**
* Login
* Guest Login
* Registration
* Custom Registration
* Customer profile
* Change email
* Change password
* Recover password
* Reset password
* Address creation
* Address editing
* Product reviews
* Newsletter registration (CMS)
* Contact form (CMS)

___
# Next Major Version Changes

## New local form handling
To make forms more accessible, we overhauled the form handling in the Storefront, which includes local form validation and best-practices for user feedback.

### New validation service and form handler plugin
There is a new central form validation class that is also available as a default instance under `window.formValidation`. This is used by a new form handler plugin that will automatically implement the necessary events and handling on a form element. It can be activated with the `data-form-handler="true"` attribute on a form element.

**Example:**
```HTML
<form action="/newsletter" method="post" data-form-handler="true">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" data-validation="required,email">
    

    <button type="submit">Submit</button>
</form>
```
The form validation works with an associated `data-validation` attribute on the form fields. You can pass a comma separated list of validator keys. Their priority is defined by their order. Only the validation message of the highest applying validator is shown to the user for relevant feedback.

These validators are available by default:

| Key      | Description |
| -------- | ------- |
| `required` | Checks if the field is not empty. |
| `email` | Checks the value of the field to be a valid email address. |
| `conformation` | Checks if the value of a confirmation field matches the value of the original field. Make sure to use the right ID naming for the validator to work. As an example, the orginal field has the ID `email` and the confirmation field has the ID `emailConfirmation`. The `confirmation` validator should be added to the confirmation field. Note that unnecessary inputs are seen as not accessible and should be avoided wherever possible. |
| `minLength` | Checks the value of the field for a minimum length. If available the validator will use the `minlength` attribute of the field to validate against. Otherwise, it will use the default configuration of eight characters. | 

You can add your own custom validators via the global `formValidation` class.

```JavaScript
window.formValidation.addValidator('custom', (value, field) => {
    // You custom validation. Should return a boolean.
}, 'Your custom validation message.');
```

You can take a look at the reference documentation of the service and the plugin for further information.

### New form field components in Twig
To make it easier to implement all best practices without recreating a lot of boilerplate code for every form field, we created new templates for different field types which can be used for easy form field rendering. You can find them in `views/storefront/components/form/`. These components work in association with the described local form handling but also the additional server-side validation.

**Example usage:**
```TWIG
<form action="/newsletter" method="post" data-form-handler="true">

    {% sw_include '@Storefront/storefront/component/form/form-input.html.twig' with {
        type: 'email',
        label: 'account.personalMailLabel'|trans|sw_sanitize,
        id: 'personalMail',
        name: 'email',
        value: data.get('email'),
        autocomplete: 'section-personal email',
        violationPath: '/email',
        validationRules: 'required,email',
        additionalClass: 'col-sm-6',
    } %}

    <button type="submit">Submit</button>
</form>
```

### Updated Shopware standard forms
The existing forms in the Shopware Storefront are already reworked to use the described practices and services. 

**Forms that are affected by the changes:**
* Login
* Guest Login
* Registration
* Custom Registration
* Customer profile
* Change email
* Change password
* Recover password
* Reset password
* Address creation
* Address editing
* Product reviews
* Newsletter registration (CMS)
* Contact form (CMS)
