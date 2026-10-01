---
title: Refactor of document generation
date: 2026-03-17
area: after-sales
tags: [core, documents]
---

## Context

Features for Accessible Documents (HTML) and E-Invoices (Zugferd) were implemented under tight legal deadlines and constrained
by backward compatibility with SW6.6 and no breaks in SW6.7.
This resulted in a technically complex and challenging-to-maintain implementation,
where these additional requirements were patched in rather than being addressed with the proper architecture.

### Some of the issues with the current implementation

- The codebase is hard to reason about and extend. This can be subjective.
- There is no clear separation of concerns between the different document types and their formats.
  - Each Zugferd document is implemented as an extra document type, rather than as a format of an existing document type.
  - Each document entity can have an accessibility media file attached, rather than having it represented as its own format of a document type.
  - This results in hard-coded file and document types in multiple locations such as the UI, where all invoice types need to be
    handled uniformly.
- The public API surface for third-party extensions is overly complicated. This can be subjective.
  - Adding new document types or formats requires disproportionate effort.
  - We think we can provide a better API surface than the existing one.
- Apps cannot currently provide custom document generators or hook into existing ones to modify their output
  (see [shopware#9676](https://github.com/shopware/shopware/issues/9676) and
  [shopware#10478](https://github.com/shopware/shopware/issues/10478)).
- The Zugferd library we use, [horstoeko/zugferd](https://github.com/horstoeko/zugferd), has been declared legacy.
  - This means that in the future we will likely have to migrate to a successor or something else.
  - Additionally, exposing its builder pattern to third-party extensions is not the cleanest interface,
    especially for apps. Right now it is wrapped behind a Shopware `ZugferdBuilder`,
    but that also does not expose all features.

### Goals for the new implementation

- [ ] One document type (e.g. invoice) can have one or more “formats” (e.g. PDF, HTML, Zugferd-XML, Zugferd-Embedded-PDF, ...).
  The merchant can choose which formats to generate.
- [ ] Different formats of the same document (same generation call) should all be based on the same data
  (e.g. all have the same document number) and are just different representations of the same data.
- [ ] Document formats can depend on one another (e.g. Zugferd-PDF can reuse the content of the PDF format +
  Zugferd-XML format to merge them into a single PDF file, or the PDF format can
  be based on the HTML content using DomPDF to convert it).
- [ ] Mail attachments should work on the "document level", meaning they should include all available formats automatically with
  the future possibility to select them precisely.
- [ ] Document types and formats should be extendable (e.g. a plugin might want to add more data to an e-invoice,
  provide another format, or even another type).
- [ ] For merchants, document generation can mostly be configured as it is now. However, the merchant should only be
  able to configure things that are actually used by the document type (e.g. why specify payment due date for
  delivery notes, and optionally also extract company details into their own entity instead of duplicating that data in every config).
- [ ] Merchants, integrations, and extensions should still be able to upload document files for a type and
  bypass our generation, as is possible now (see `document.static` DB field).
- [ ] This new implementation should be "opt-in" during 6.7, so third parties have time to adjust, and it
  will replace the old implementation in 6.8 (which means that the old one should be deprecated).

## Decision

We will refactor the document generation codebase to make it more maintainable and extensible.
It will lead to better separation of concerns between document types and their formats,
as well as a better API surface for extensions, so they do not rely on PHP class decoration,
break easily with each Shopware major release, and block future internal improvements.

The new implementation will be opt-in during 6.7 and will become the default in 6.8, replacing
the existing (old) implementation. More details on the concrete migration strategy will be described in a separate ADR,
after the actual implementation is mostly done.

With the new implementation we will also generate Zugferd XML data via Twig templates, which will provide better
extendability, and we remove the `horstoeko/zugferd` dependency.

The architecture of the new implementation will be described in a separate ADR, which you can find here:
[2026-03-18-new-document-generation-architecture.md](https://github.com/shopware/shopware/blob/trunk/adr/2026-03-18-new-document-generation-architecture.md)

The new extension points are described here:
[2026-03-19-new-document-generation-extension-points.md](https://github.com/shopware/shopware/blob/trunk/adr/2026-03-19-new-document-generation-extension-points.md)

## Consequences

We expect:

- Only minor changes to the merchant UX when generating documents, configuring them, or building flows for them.
  But we are not limiting ourselves to this and might adjust things to provide a better UX overall.
- That all existing (already generated) documents will be migrated and will still be accessible untouched in the new implementation UI.
- All extensions and integrations that did anything document-related will have to be updated to use the new implementation.

### Estimated impact on customers

We scanned the codebases of plugins available in our extension store and summarized the results below.

We make the following assumptions:
- In total, 204 of the 3,204 scanned plugins use some part of the existing document generation API surface. That is 6.4% of the plugin ecosystem.
- The 84 plugins that touch only the Twig templates are probably unaffected, since we will continue using the same templates
  and will try not to introduce breaking changes to them. This reduces the estimated number of affected plugins to 120.
- If we keep the current configuration database schemas backward compatible, we could reduce that number by a further 51 plugins,
  bringing the estimated number of affected plugins down to 69.
  - It turns out that some plugins use only our configuration schemas and do not actually use our rendering system,
    but instead build their own.

With these assumptions, we estimate that this refactor would break 69 plugins, which is 2.2% of our plugin ecosystem.
Of course, these numbers are only estimates, and not every plugin is published in our extension store.

#### Overview

| metric | value |
|---|---:|
| plugins scanned | 3204 |
| plugins using any extension point | 204 |
| unique plugin/extension-point usages | 553 |
| average extension points per matching plugin | 2.71 |

#### Extension Point Groups

| group | contains | plugins | group-only | shared with other groups/points | share of matching plugins |
|---|---|---:|---:|---:|---:|
| twig templates (any) | @Framework/documents/base.html.twig, credit_note.html.twig, delivery_note.html.twig, invoice.html.twig, storno.html.twig | 117 | 84 | 33 | 57.4% |
| renderers/builders (any) | document.renderer, AbstractDocumentRenderer, CreditNoteRenderer, DeliveryNoteRenderer, InvoiceRenderer, StornoRenderer, ZugferdBuilder, ZugferdRenderer | 39 | 2 | 37 | 19.1% |
| type renderers (any) | document_type.renderer, AbstractDocumentTypeRenderer, HtmlRenderer, PdfRenderer | 30 | 4 | 26 | 14.7% |
| config entities (any) | document_base_config, document_base_config_sales_channel | 77 | 51 | 26 | 37.7% |

#### Per Extension Point

| extension point | plugins | exclusive | shared | share of matching plugins |
|---|---:|---:|---:|---:|
| document.renderer | 19 | 1 | 18 | 9.3% |
| document_type.renderer | 4 | 0 | 4 | 2.0% |
| AbstractDocumentRenderer | 19 | 0 | 19 | 9.3% |
| AbstractDocumentTypeRenderer | 4 | 1 | 3 | 2.0% |
| CreditNoteRenderer | 7 | 0 | 7 | 3.4% |
| DeliveryNoteRenderer | 10 | 0 | 10 | 4.9% |
| DocumentFileRendererRegistry | 11 | 0 | 11 | 5.4% |
| DocumentGenerateOperation | 44 | 11 | 33 | 21.6% |
| HtmlRenderer | 4 | 0 | 4 | 2.0% |
| InvoiceRenderer | 28 | 0 | 28 | 13.7% |
| PdfRenderer | 28 | 2 | 26 | 13.7% |
| StornoRenderer | 7 | 0 | 7 | 3.4% |
| ZugferdBuilder | 0 | 0 | 0 | 0.0% |
| ZugferdRenderer | 0 | 0 | 0 | 0.0% |
| @Framework/documents/base.html.twig | 52 | 20 | 32 | 25.5% |
| credit_note.html.twig | 28 | 0 | 28 | 13.7% |
| delivery_note.html.twig | 48 | 2 | 46 | 23.5% |
| invoice.html.twig | 76 | 21 | 55 | 37.3% |
| storno.html.twig | 33 | 0 | 33 | 16.2% |
| document_base_config | 77 | 12 | 65 | 37.7% |
| document_base_config_sales_channel | 54 | 0 | 54 | 26.5% |
