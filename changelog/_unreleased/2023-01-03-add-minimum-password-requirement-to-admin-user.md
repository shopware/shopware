---
title: Add minimum password requirement to admin user
issue: NEXT-24747
---
# Core
* Added a new migration `Migration1672743034AddDefaultAdminUserPasswordMinLength` to add default config value `core.userPermission.passwordMinLength` for default user's password min length
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField` to added a new argument in constructor to allow indicating which entity that PasswordField belongs to
* Changed method `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer::getConstraints` to validate length of password from system config if PasswordField is `admin` or `customer` password
* Changed `\Shopware\Core\System\User\UserDefinition` to inject `admin` as a new parameter of `PasswordField`
* Changed `\Shopware\Core\Checkout\Customer\CustomerDefinition` to inject `customer` as a new parameter of `PasswordField`
* ___
# Administration
* Changed `src/module/sw-users-permissions/page/sw-users-permissions-user-detail/index.js` and its template to show password validation error when editing a user
* Changed `src/module/sw-login/view/sw-login-recovery-recovery/index.js` and its template to show password validation error when recovering user password
* Removed snippet `sw-customer.detail.notificationPasswordLengthErrorMessage` due to unused
* Changed method `validPassword` in `src/module/sw-customer/page/sw-customer-detail/index.js` as we validate the length server-side
* Deprecated data property `defaultMinPasswordLength` in `src/Administration/Resources/app/administration/src/module/sw-customer/page/sw-customer-create/index.js` due to unused
* Deprecated computed property `validPasswordField` in `src/Administration/Resources/app/administration/src/module/sw-customer/page/sw-customer-create/index.js` due to unused
* Deprecated method `getDefaultRegistrationConfig` in `src/Administration/Resources/app/administration/src/module/sw-customer/page/sw-customer-create/index.js` due to unused
* Changed template `src/Administration/Resources/app/administration/src/module/sw-customer/component/sw-customer-card/sw-customer-card.html.twig` to display password validation error
* Added `sw-users-permissions-configuration` component in `sw-users-permissions` module to show the component itself in the module
* Added the following data variables in `sw-users-permissions` component:
    * `isLoading`
    * `isSaveSuccessful`
* Added the following methods in `sw-users-permissions` component:
    * `onChangeLoading`
    * `onSave`
    * `onSaveFinish`
* Changed ACL config in `sw-users-permissions` module
