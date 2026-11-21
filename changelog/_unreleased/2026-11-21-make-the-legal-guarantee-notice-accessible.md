---
title: Make the legal guarantee notice accessible
issue: 23003
---
# Core
* Added `Shopware\Core\Content\LegalGuaranteeNotice\LegalGuaranteeNoticeRenderer::renderTextForLanguage()` and the Twig filter `sw_legal_guarantee_notice_text`, which render the text of the legal guarantee notice in the language of its SVG, taken from the European Commission's PDF version of the notice.
* Changed the legal guarantee notice SVGs to be hidden from assistive technology.
___
# API
* Changed the Store API route `GET /store-api/legal-guarantee-notice` to also return `text`: the notice's text as HTML, in the language of `svg`.
* Added the optional parameter `text` to the constructor of `Shopware\Core\Content\LegalGuaranteeNotice\SalesChannel\LegalGuaranteeNoticeRouteResponse`.
___
# Storefront
* Added the snippet `checkout.confirmLegalGuaranteeNoticeYourEuropeLink`, which labels the legal guarantee notice link on the checkout confirm page and in the privacy notice modal, and says that it opens a new tab.
* Added the snippet `global.default.close`, which labels the close button of the legal guarantee notice modals on the checkout confirm page and in the privacy notice.
* Added the blocks `page_checkout_confirm_legal_guarantee_notice_inline_text`, `page_checkout_confirm_legal_guarantee_notice_modal_text` and `component_privacy_legal_guarantee_notice_modal_text`, which render the notice text visually hidden. The notice link is described by it.
___
# Upgrade Information
## The legal guarantee notice has a text alternative
The legal guarantee notice SVG is now hidden from assistive technology. Its link is labelled with the snippet `checkout.confirmLegalGuaranteeNoticeYourEuropeLink` and described by the notice text, which the new `*_legal_guarantee_notice_*_text` blocks render visually hidden.
If your theme overrides the notice link blocks `page_checkout_confirm_legal_guarantee_notice_inline_link`, `page_checkout_confirm_legal_guarantee_notice_modal_body` or `component_privacy_legal_guarantee_notice_modal_body`, add the `aria-label` and `aria-describedby` attributes there as well.
Headless frontends should render the new `text` of `GET /store-api/legal-guarantee-notice` as the text alternative of the SVG, for example visually hidden next to it.
