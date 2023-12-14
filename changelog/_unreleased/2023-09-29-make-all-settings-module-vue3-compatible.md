---
title: Make all settings module Vue3 compatible
issue: NEXT-29011
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added `label` to `sw-base-field` root element
* Added manually the props `is-inheritance` and `is-inherited` to `sw-base-field` in `sw-checkbox-field` because Vue3 does not support direct access to it with the `field.mixin`
* Added manual binding in `sw-number-field` to `sw-contextual-field` which also includes the `inheritanceAttrs`
* Added manual binding in `sw-password-field` to `sw-contextual-field` which also includes the `inheritanceAttrs`
* Added manual binding in `sw-select-field` to `sw-contextual-field` which also includes the `inheritanceAttrs`
* Added manual binding in `sw-switch-field` to `sw-contextual-field` which also includes the `inheritanceAttrs`
* Added manual binding in `sw-text-field` to `sw-contextual-field` which also includes the `inheritanceAttrs`
* Added manual binding in `sw-textarea-field` to `sw-contextual-field` which also includes the `inheritanceAttrs`
* Added manual binding in `sw-url-field` to `sw-contextual-field` which also includes the `inheritanceAttrs`
* Changed in `sw-admin-menu` the `key` binding from component to template with loop because Vue3 want keying directly on the v-for template
* Changed in `sw-admin-menu-item` the `key` binding from component to template with loop because Vue3 want keying directly on the v-for template
* Changed in `sw-modals-renderer` the `key` binding from component to template with loop because Vue3 want keying directly on the v-for template
* Changed in `sw-search-bar` the `key` binding from component to template with loop because Vue3 want keying directly on the v-for template
* Changed the `key` for the target label in `sw-duplicated-media-v2` because it was identical in the v-if-else statement which leads to errors
* Changed in `sw-notification-center-item` the `key` binding from component to template with loop because Vue3 want keying directly on the v-for template
* Changed in `sw-notifications` the `key` binding from component to template with loop because Vue3 want keying directly on the v-for template
* Changed the generic `v-model` in `sw-custom-field-set-detail-base` for `sw-custom-field-translated-labels` to `v-model:config`
* Removed the `v-model` in `sw-settings-customer-group-detail` and replaced it with `:value` because the given value was readonly and couldn't be changed anyway (also leads to hard errors in Vue3)
* Changed in `sw-settings-document-detail` the `key` binding from component to template with loop because Vue3 want keying directly on the v-for template
* Changed the `v-model` in `sw-settings-mailer` to `v-model:value`
* Added `<div style="display: none;">` to `sw-settings-snippet-list` to fix a weird Vue3 DOM patching error
* Changed `@change` to `@update:value` in `sw-settings-tax-detail` at the `sw-text-field` component
* Changed in `sw-settings-tax-list` the `key` binding from component to template with loop because Vue3 want keying directly on the v-for template
* Added custom warn handler in `vue.adapter` for Vue3 to fail hard on compiler errors in watch mode to have a consistent behavior between production and development mode
* Changed `mediaId` property in `sw-media-field` to `value` and added a computed property `mediaId` which maps the `value` to `mediaId` and emits a correct event back
* Changed `v-model` in `sw-boolean-radio-group` to `v-model:value`
* Added `label` property to `sw-checkbox-field`
* Changed timing behavior in `sw-url-field` to match the Vue3 behavior to Vue2
* Changed timing behavior in `sw-wizard` to match the Vue3 behavior to Vue2
* Changed `$parent` emit calling in `sw-wizard-page` to take also the AsyncComponentWrapper into account
* 
