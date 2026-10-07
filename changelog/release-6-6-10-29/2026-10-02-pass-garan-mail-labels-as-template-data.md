---
title: Pass GARAN mail labels as template data instead of a Twig filter
issue: 20983
---
# Core
* Changed `Shopware\Core\Content\Product\Garan\GaranLabelMailSubscriber` to pass the GARAN labels of an order's products to mail templates as `garanLabels` on `MailBeforeValidateEvent`, so mail templates no longer need a Twig filter that nodes running older code do not know during a blue-green deployment.
* Changed the `order_confirmation_mail` templates to read the GARAN label from `garanLabels` instead of the `sw_garan_label_mail` filter.
* Added migration `Shopware\Core\Migration\V6_6\Migration1790751498ReadGaranLabelFromMailTemplateData`, which re-applies the order confirmation mail template for shops that did not edit it.
___
# Upgrade Information
## GARAN labels in mails come from the `garanLabels` template variable
The order confirmation mail reads the GARAN label from the new `garanLabels` template variable. The `sw_garan_label_mail` Twig filter keeps working. A migration updates the template for shops that never edited it.

If you customized the order confirmation mail, we recommend to replace `nestedItem.productId|sw_garan_label_mail(context)` with `garanLabels[nestedItem.productId] ?? null`, and `lineItem.productId|sw_garan_label_mail(context)` with `garanLabels[lineItem.productId] ?? null` in the plain text version. `garanLabels` is passed to every mail template that references it and has an `order` in its data.
