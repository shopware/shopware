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

- The codebase is hard to reason about and extend (can be subjective)
- There is no clear separation of concerns between the different document types and their formats
  - each Zugferd document is implemented as an extra document type, rather than a format of an existing document type
  - each document entity can have an a11y media file attached rather than it being its own format of a document type
  - this results in hard-coded file / document types in multiple locations such as the UI, where all invoice types need to be
    handled uniformly
- Public API surface for third-party extensions is overly complicated (can be subjective)
  - Adding new document types or formats requires disproportionate effort
  - We think we can provide a better API surface than the existing one
- Apps cannot currently provide custom document generators or hook into existing ones to modify their output
  (see [shopware#9676](https://github.com/shopware/shopware/issues/9676) and
  [shopware#10478](https://github.com/shopware/shopware/issues/10478))
- The Zugferd library we use [horstoeko/zugferd](https://github.com/horstoeko/zugferd) declared a legacy status
  - means in the future we likely have to migrate to a successor / something else
  - additionally exposing its builder pattern to third party extensions isn't the cleanest
    interface (especially difficult for Apps), right now it is wrapped behind a Shopware `ZugferdBuilder`,
    but that also doesn't expose all features.

### Goals for the new implementation

- [ ] One document type (e.g. invoice) can have one or more “formats” (e.g. PDF, HTML, Zugferd-XML, Zugferd-Embedded-PDF, ...).
  The merchant can choose which "formats" to generate.
- [ ] Different formats of the same document (same generate call) should all be based on the same data
  (e.g. all have the same document number) and are just different representations of the same data.
- [ ] Document formats can depend on one another (e.g. Zugferd-PDF can reuse the content of the PDF format +
  Zugferd-XML format to merge them into a single PDF file, or the PDF format can
  be based on the HTML content using DomPDF to convert it).
- [ ] Mail attachments should work on "document level", means they should include all available formats automatically with
  the future possibility to select them precisely.
- [ ] Documents types + formats should be extendable (e.g. a plugin might want to add more data to an E-Invoice,
  provide another format, or even another type)
- [ ] For merchants, the document generation can mostly be configured like it is now. But the merchant should only be
  able to configure things that are actually used by the document type (e.g. why specify payment due date for
  delivery notes, optionally also extract company details in its own entity instead of duplicating that data in every config)
- [ ] Merchant (+Integrations+Extensions) should still be able to upload document files for a type and
  bypass our generation, like it is now possible (see `document.static` DB field)
- [ ] This new implementation should be "opt-in" during 6.7, so third parties have time to adjust and
  will replace the old implementation with 6.8 (means that one should be deprecated).

## Decision

We will refactor the document generation codebase to make it more maintainable and extensible.
The new implementation will be opt-in during 6.7 and will become the default in 6.8, replacing
the existing (old) implementation.

With the new implementation we will also generate Zugferd XML data via Twig templates, which will provide better
extendability, and we remove the `horstoeko/zugferd` dependency.

The architecture of the new implementation will be described in a separate ADR, which you can find here:
[2026-03-18-new-document-generation-architecture.md](https://github.com/shopware/shopware/blob/trunk/adr/2026-03-18-new-document-generation-architecture.md)

As well as the new extension points here:
[2026-03-19-new-document-generation-extension-points.md](https://github.com/shopware/shopware/blob/trunk/adr/2026-03-19-new-document-generation-extension-points.md)

## Consequences

We expect:

- Only minor changes to the merchant UX when generating documents, configuring them, or building flows for them.
- That all existing (already generated) documents will be migrated and will still be accessible untouched in the new implementation UI.
- All Extensions and Integrations that did anything document-related will have to be updated to use the new implementation.
