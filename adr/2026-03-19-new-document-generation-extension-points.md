---
title: New document generation extension points
date: 2026-03-19
area: after-sales
tags: [core, documents]
---

## Disclaimer

This ADR doesn't server as documentation (and might get outdated),
the final extension points will be documented in our dev docs during implementation and maintained there.
Instead, it serves as an agreed-upon approach on how these extension points should be implemented.

## Plugin system

### Adding a new document type / format or both

Essentially doing the same thing as the core does internally for the default document types / formats.

1. Add a DB migration to add the new document type / format to the corresponding table, so
  it can be selected, for example, in the admin.
2. Add your Renderer, which extends `AbstractDocumentRenderer` and is tagged
  with the `shopware.documentV2.renderer` tag to the DI container.
  - It can also make use of existing renderers output by declaring dependencies on other formats.

### Adjusting the generated HTML / PDF content

Most likely you would override certain Twig blocks in the used document template.

If you need extra data or additional order associations, you would subscribe to event X (TBA) and add the data there.

TBA

### Adjusting the generated Zugferd XML content

TBA

## App system

TBA. Likely something like the
[checkout gateway](https://developer.shopware.com/docs/guides/plugins/apps/gateways/checkout/checkout-gateway.html#checkout-gateway-endpoint)
where shopware reaches out to the app backend and gets some data back.
An alternative could be a push system, where the app backend sets,
e.g., custom fields that are later used in the templates.

### Adding a new document type / format or both

TBA

### Adjusting the generated HTML / PDF content

TBA

### Adjusting the generated Zugferd XML content

TBA
