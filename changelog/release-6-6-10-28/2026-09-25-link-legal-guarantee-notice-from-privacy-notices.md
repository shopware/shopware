---
title: Link legal guarantee notice from privacy notices
issue: 20771
---
# Storefront
* Added the legal guarantee notice paragraph and modal to `component/privacy-notice.html.twig`, shown on the registration form when `core.cart.showLegalGuaranteeNotice` is enabled, independent of `core.loginRegistration.requireDataProtectionCheckbox`.
* Added the blocks `component_privacy_legal_guarantee_notice_paragraph`, `component_privacy_legal_guarantee_notice_modal`, `component_privacy_legal_guarantee_notice_modal_header` and `component_privacy_legal_guarantee_notice_modal_body` to `component/privacy-notice.html.twig`.
