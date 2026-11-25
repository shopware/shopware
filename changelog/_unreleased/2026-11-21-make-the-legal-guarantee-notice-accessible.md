---
title: Make the legal guarantee notice accessible
issue: 23003
---
# Core
* Added `Shopware\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeRenderer::renderTextForLanguage()` and the Twig filter `sw_legal_guarantee_notice_text`, which render the notice's text in the language of its SVG.
* Changed the legal guarantee notice SVGs to be hidden from assistive technology.
___
# API
* Changed `GET /store-api/legal-guarantee-notice` to also return the notice's text as HTML in `text`.
* Added the optional constructor parameter `text` to `Shopware\Core\Content\LegalGuaranteeNotice\SalesChannel\LegalGuaranteeNoticeRouteResponse`.
___
# Storefront
* Added the snippets `checkout.confirmLegalGuaranteeNoticeYourEuropeLink` for the notice link and `global.default.close` for the close button of the notice modals.
* Added the blocks `page_checkout_confirm_legal_guarantee_notice_inline_text`, `page_checkout_confirm_legal_guarantee_notice_modal_text` and `component_privacy_legal_guarantee_notice_modal_text`, which render the notice text visually hidden as the link's description.
___
# Upgrade Information
## The legal guarantee notice has a text alternative
Themes that override `page_checkout_confirm_legal_guarantee_notice_inline_link`, `page_checkout_confirm_legal_guarantee_notice_modal_body` or `component_privacy_legal_guarantee_notice_modal_body` should take over the link's new `aria-label` and `aria-describedby`, and in the modal bodies also the new `*_modal_text` block.
Headless frontends should render the new `text` of `GET /store-api/legal-guarantee-notice` as the SVG's text alternative, for example visually hidden next to it.
