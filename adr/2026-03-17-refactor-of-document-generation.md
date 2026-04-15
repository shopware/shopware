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
