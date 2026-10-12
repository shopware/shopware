---
title: Attach the producer's guarantee terms to GARAN products
issue: 20352
---
# Core
* Added the inherited fields `guaranteeTermsMediaId`, `guaranteeTermsMedia` and `guaranteeTermsUrl` to `Shopware\Core\Content\Product\ProductDefinition`, and the association `productGuaranteeTerms` to `Shopware\Core\Content\Media\MediaDefinition`.
* Changed `Shopware\Core\Content\Product\Garan\GaranLabelProductValidator` to reject a `guaranteeTermsUrl` that is not an `http://` or `https://` URL with the `INVALID_GARAN_GUARANTEE_TERMS_URL` violation.
* Changed `Shopware\Core\Content\Product\Garan\GaranLabelMailSubscriber` to add a `termsUrl` key to each `garanLabels` entry, and to attach the guarantee terms PDFs of the products to mails that reference `garanLabels`.
* Changed the `order_confirmation_mail` templates to link the guarantee terms below the GARAN label.
* Added migration `Shopware\Core\Migration\V6_6\Migration1790906511AddGaranGuaranteeTerms`, which adds the columns and re-applies the order confirmation mail template for shops that did not edit it.
___
# Administration
* Added the guarantee terms PDF and URL fields to `sw-product-guarantee-form`.
* Changed `sw-media-field` to render its label only when one is given.
___
# Storefront
* Added the block `buy_widget_garan_label_terms_link` to `@Storefront/storefront/component/buy-widget/buy-widget.html.twig`, which links the guarantee terms below the GARAN label.
* Changed `Shopware\Storefront\Page\Product\ProductPageLoader` to load the `guaranteeTermsMedia` association.
___
# Upgrade Information
## GARAN guarantee terms per product
Products have new fields for the guarantee terms, inherited by variants: `guaranteeTermsMediaId` for a PDF and `guaranteeTermsUrl` for a web page. `guaranteeTermsUrl` only accepts `http://` and `https://` URLs; other values are rejected with the `INVALID_GARAN_GUARANTEE_TERMS_URL` violation.

Each `garanLabels` entry has a new `termsUrl` key: the URL, or the PDF's URL if no URL is set. Mails that reference `garanLabels` attach the PDFs of the products. A migration adds the terms link to the order confirmation mail for shops that never edited it.
If you customized the template, add the link below the GARAN label:

```twig
{% if garanLabel.termsUrl %}<a href="{{ garanLabel.termsUrl }}">Guarantee terms</a>{% endif %}
```
