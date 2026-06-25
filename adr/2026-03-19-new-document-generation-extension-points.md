---
title: New document generation extension points
date: 2026-03-19
area: after-sales
tags: [core, documents]
---

## Disclaimer

This ADR does not serve as documentation (and might get outdated).
The final extension points will be documented in our dev docs during implementation and maintained there.
Instead, it serves as an agreed-upon approach for how these extension points should be implemented.

If you have not already, you should read
[2026-03-17-refactor-of-document-generation.md](https://github.com/shopware/shopware/blob/trunk/adr/2026-03-17-refactor-of-document-generation.md)
and [2026-03-18-new-document-generation-architecture.md](https://github.com/shopware/shopware/blob/trunk/adr/2026-03-18-new-document-generation-architecture.md)
first, to understand the reasoning behind and goals of the new document generation as well as the overall architecture.

## Plugin system

The plugin system can take advantage of tagged Symfony services, similar to how the core document types + formats
are implemented, as well as use the Twig template extension system.

### Adjusting the generated HTML / PDF / XML content and presentation

1. (Optional) Add a DataProvider, which extends `AbstractDocumentDataProvider` and is tagged
   with the `shopware.documentV2.provider` tag to the DI container. It allows you to enrich the order with extra associations and add extra data to certain document types.
2. Extend the Twig templates you are interested in. You have access to all data passed into the template,
   including data from your own data providers, and can extend or override Twig blocks.

### Adding a new document format

1. (Optional) Add a DataProvider, which extends `AbstractDocumentDataProvider` and is tagged
   with the `shopware.documentV2.provider` tag to the DI container. It allows you to enrich the order with extra associations and add extra data to certain document types.
2. Add your Renderer, which extends `AbstractDocumentRenderer` and is tagged
   with the `shopware.documentV2.renderer` tag to the DI container.
   - It can also make use of existing renderers' output by declaring dependencies on other formats.

### Adding a new document type

1. (Required) Add a DataProvider, which extends `AbstractDocumentDataProvider` and is tagged
   with the `shopware.documentV2.provider` tag to the DI container.
   It allows you to enrich the order with extra associations and add extra data for your document type.
   But more importantly, it allows you to register your new document type in the system, making it available for
   selection, e.g., in the administration.
2. Provide Twig templates for HTML and optionally XML to make use of our default `HtmlRenderer` and `ZugferdXmlRenderer`.
   You can take advantage of the `PdfRenderer` as well (which only needs HTML output), without writing any rendering code
   yourself besides the Twig templates.

## App system

The app system can only take advantage of the Twig template extension system, which Shopware themes also use,
which makes it less powerful than the plugin counterpart.

One option to add more data to a document could be to add custom fields on the order entity
(e.g. via the AdminSDK or App backend and webhooks),
which we load by default and apps can just use in their Twig template customizations.

Another option is to add an app script to enrich the order with extra associations and add extra render input data.
The app script should allow the following:
- Enrich the order with extra associations, like the plugin system allows.
- Query extra data with repositories.
- Return arbitrary data (associative array), which will be passed to the renderers and Twig templates.

To still allow further customization, there will be additional webhooks.

### Adjusting the generated HTML / PDF / XML content and presentation

1. (Optional) Add custom field data to an order entity.
2. (Optional) Add an app script to enrich the order with extra associations and add extra data.
3. Extend the Twig templates you are interested in. You have access to all data passed into the template,
   including order custom fields, and you can extend or override Twig blocks.

### Adding a new document type + format

1. Add your desired document type and format(s) to your app manifest, so they can be selected, for example, in the admin.
2. Subscribe to the webhook (document gateway, similar to the
  [checkout gateway](https://developer.shopware.com/docs/guides/plugins/apps/gateways/checkout/checkout-gateway.html))
   `TBA`. You are responsible for:
   - Generating the specified document type in all specified format(s) from scratch.
   - Uploading the document file(s) back to Shopware.
   - Having the document file(s) stored in Shopware as static documents,
     similar to how merchants can bypass our generation and upload documents directly.
   - You have strict time constraints to perform all of the above, so your document artifacts can be used by Shopware,
     for example, in Flow Builder and included in customer mail.
   - You have to return the generated `document` ID in the webhook or gateway HTTP response.

## Twig templates

Twig templates will have access to the following data:
- The `RenderInput`.
  It includes:
  - Document type.
  - Document number.
  - Order entity including all loaded associations.
  - Any extra data provided by the DataProviders.
- The usual Shopware Twig extensions.
  Examples include:
  - `config` function to look up system config values.
  - `theme_config` function to look up theme config values.
