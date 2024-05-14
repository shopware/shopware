---
title: CustomField label loading in storefront
date: 2020-09-08
area: storefront
tags: [custom-fields, storefront, snippets]
---

## Context

We want to provide the labels of custom fields in the storefront to third party developers.
On one hand we could add the labels to every loaded entity, but this will cause a heavy leak of performance and the labels
are often not used in the template.

## Decision

We implemented a subscriber, which listen on the `custom_field.written` event to add also snippets to all snippet sets with
the given label translations of the custom field. The `translationKey` of the snippets are prefixed with `customFields.`,
followed by the technical name of the custom field. Thus the snippets can be used in the storefront.

## Consequences

Inserting a custom field always creates new snippet with the given label translations.
