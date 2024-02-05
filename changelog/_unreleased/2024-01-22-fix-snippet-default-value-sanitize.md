---
title: Fix snippet default value sanitize
issue: NEXT-30489
author: Malte Janz
author_email: m.janz@shopware.com
author_github: MalteJanz
---
# Storefront
* Changed the snippet sanitizer to allow the attribute `data-prev-url` in `src/Core/Framework/Resources/config/packages/shopware.yaml`
* Removed deprecated translation key `general.privacyNotice`. Use `general.privacyNoticeText` instead.
* Removed deprecated translation key `account.profileDelete`. Use `account.profileDeleteText` instead.
* Removed deprecated translation key `checkout.confirmTerms`. Use `checkout.confirmTermsText` instead.
* Removed deprecated translation key `checkout.confirmTermsReminder`. Use `checkout.confirmTermsReminderText` instead.
* Removed deprecated translation key `contact.privacyNotice`. Use `contact.privacyNoticeText` instead.
* Removed deprecated translation key `footer.serviceContactLink`. Use `footer.serviceContactText` instead.
* Removed deprecated translation key `footer.includeVat`. Use `footer.includeVatText` instead.
* Removed deprecated translation key `footer.excludeVat`. Use `footer.excludeVatText` instead.
* Removed deprecated translation key `cookie.message`. Use `cookie.messageText` instead.
* Removed deprecated translation key `component.cms.vimeo.privacyNotice`. Use `component.cms.vimeo.privacyNoticeText` instead.
