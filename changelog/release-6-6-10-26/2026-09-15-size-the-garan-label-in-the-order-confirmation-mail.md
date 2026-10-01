---
title: Size the GARAN label in the order confirmation mail
issue: 20104
---
# Core
* Changed the `order_confirmation_mail` HTML fixtures in `src/Core/Migration/Fixtures/mails/order_confirmation_mail/`: the GARAN label image now carries explicit `width="195" height="30"` attributes and a translated `alt` text, and renders inside the line item's description cell instead of in a full width row of its own. The line item table gained `cellpadding="6" cellspacing="0"`.
* Added `Shopware\Core\Migration\V6_6\Migration1788531858FixGaranLabelInOrderConfirmationMail`, which re-applies the `order_confirmation_mail` template for shops that never edited it.
___
# Upgrade Information
## GARAN label in the order confirmation mail is sized and sits next to the line item
The GARAN label added to the `order_confirmation_mail` template with the EU harmonised guarantee labelling rendered without dimensions on a full width row of its own, so mail clients scaled the SVG data URI up to the width of the mail and cut it off.

As with the original change, the migration re-applies the template only for shops that never edited their order confirmation mail template. If you customised that template and copied the label markup, replace your `<tr><td colspan="6">` label row with the markup from `src/Core/Migration/Fixtures/mails/order_confirmation_mail/en-html.html.twig`.

Note that the label is embedded as an SVG `data:` URI, which Gmail and Outlook do not render at all. Recipients on those clients see the `alt` text; the label remains visible in the storefront and in the customer account.
