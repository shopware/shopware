---
title: Fix console error on AjaxModal
issue: NEXT-24448
---
# Storefront
* Deprecated selector `[data-bs-toggle="modal"][data-url]` to initialize the `AjaxModal` plugin. Use selector `[data-ajax-modal][data-url]` instead.
* Deprecated translation key `general.privacyNotice`. Use `general.privacyNoticeText` instead.
* Deprecated translation key `account.profileDelete`. Use `account.profileDeleteText` instead.
* Deprecated translation key `checkout.confirmTerms`. Use `checkout.confirmTermsText` instead.
* Deprecated translation key `checkout.confirmTermsReminder`. Use `checkout.confirmTermsReminderText` instead.
* Deprecated translation key `contact.privacyNotice`. Use `contact.privacyNoticeText` instead.
* Deprecated translation key `footer.serviceContactLink`. Use `footer.serviceContactText` instead.
* Deprecated translation key `footer.includeVat`. Use `footer.includeVatText` instead.
* Deprecated translation key `footer.excludeVat`. Use `footer.excludeVatText` instead.
* Deprecated translation key `cookie.message`. Use `cookie.messageText` instead.
* Deprecated translation key `component.cms.vimeo.privacyNotice`. Use `component.cms.vimeo.privacyNoticeText` instead.
___
# Upgrade Information

## Selector to open an ajax modal
The JavaScript plugin `AjaxModal` is able to open a Bootstrap modal and fetching content via ajax.
This is currently done by using the known Bootstrap selector for opening modals `[data-bs-toggle="modal"]` and an additional `[data-url]`.
The corresponding modal HTML isn't existing upfront and will be created by `AjaxModal` internally by using the `.js-pseudo-modal-template` template.
However, Bootstrap v5 needs a `data-bs-target="*"` upfront which points to the desired HTML of a modal. Otherwise, it throws a JavaScript error because the Modal's DOM cannot be found.
The `AjaxModal` itself works regardless of the error.

Because we don't want to enforce to have an additional `data-bs-target="*"` selector everywhere and want to keep the general workflow using `AjaxModal`, we change the
selector, which is initializing the `AjaxModal` plugin, to `[data-ajax-modal][data-url]` to not interfere with the Bootstrap default modal. 
`AjaxModal` will do all modal related tasks programmatically and does not rely on Bootstraps data-attribute API.

### Before
```html
<a data-bs-toggle="modal" data-url="/my/route" href="/my/route">Open Ajax Modal</a>
```

### After
```html
<a data-ajax-modal="true" data-url="/my/route" href="/my/route">Open Ajax Modal</a>
```

___
# Next Major Version Changes

## Selector to open an ajax modal
The selector to initialize the `AjaxModal` plugin will be changed to not interfere with Bootstrap defaults data-attribute API.

### Before
```html
<a data-bs-toggle="modal" data-url="/my/route" href="/my/route">Open Ajax Modal</a>
```

### After
```html
<a data-ajax-modal="true" data-url="/my/route" href="/my/route">Open Ajax Modal</a>
```
