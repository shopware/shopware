# 2020-08-28 - CustomField label loading in storefront

## Context

We want to provide the labels of custom fields in the storefront to third party developers.
On one hand we could add the labels to every loaded entity, but this will cause a heavy leak of performance and the labels
are often not used in the template.

Because of this I would suggest, that a Twig filter is sufficient for the case, if a developer wants to have the custom field labels.

## Decision

## Consequences

A developer has to use the Twig filter if it is necessary, instead of adding the labels in advance. 
