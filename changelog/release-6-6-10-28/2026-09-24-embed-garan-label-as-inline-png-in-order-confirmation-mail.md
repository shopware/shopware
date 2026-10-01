---
title: Embed the GARAN label as inline PNG in the order confirmation mail
issue: 20527
---
# Core
* Added `Shopware\Core\Content\Product\Garan\GaranLabelInlineImage`, which composes the nested GARAN label as PNG from the artwork in `src/Core/Framework/Resources/garan`.
* Added `Shopware\Core\Content\Product\Garan\GaranLabelMailSubscriber`, which attaches the label PNGs referenced by a mail as inline parts on `MailBeforeSentEvent`.
* Added the Twig filter `sw_garan_label_mail`, which returns the `cid` reference of the label image and the formatted guarantee duration for mail templates.
* Changed the `order_confirmation_mail` templates to embed the GARAN label as inline PNG instead of an SVG `data:` URI, which Gmail and Outlook do not display, and to name the guarantee duration in the `alt` text and in the plain text mail.
* Added migration `Shopware\Core\Migration\V6_6\Migration1790078381EmbedGaranLabelInOrderConfirmationMail`, which re-applies the order confirmation mail template for shops that did not edit it.
___
# Upgrade Information
## GARAN label in the order confirmation mail is embedded as an inline PNG
The order confirmation mail now attaches the GARAN label as an inline PNG instead of an SVG `data:` URI, which Gmail and Outlook do not display. If you customized that template, replace `sw_garan_label_nested_uri` with the new `sw_garan_label_mail` filter as shown in `src/Core/Migration/Fixtures/mails/order_confirmation_mail/en-html.html.twig`.
