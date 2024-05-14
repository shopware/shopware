---
title: Specify priority of translations in DAL write payloads
date: 2022-03-29
area: core
tags: [dal, translations]
---

## Context

The DAL allows to write translated values in multiple ways:
* directly on the translated field
  * as a plain string (in the language of the current context)
  * as an array indexed either by language id or iso-code indicating the language of the value
* on the `translations` association as an indexed array

The current priority of those overwrites was accidental and never formally specified.
This lead to unexpected behaviour in some cases.

## Decision

We will formally specify the priority of those translations overwrites, so the DAL works as expected and developers can rely on that priority.
In general we encourage to use `iso-codes` when writing translations for multiple languages at once.
This has the following advantages:
* The payload itself is either to understand, and errors are easier to catch by just looking at the payload.
* The payload is compatible with multiple system (where the ids for each language will be different)

Besides that the common understanding when providing a translation value on the translated field itself,
was that it should be treated as "default" value, and all values that are further specified in the associations should take precedence.

From those two observations we deduced the following rules for the priority of translation overwrites:
1. Translations indexed by `iso-code` take precedence over values indexed by `language-id`
2. Translations specified on the `translations`-association take precedence over values specified directly on the translated field.

**Note:** Rule 1 is more important than rule 2, therefore a translation value indexed by `iso-code` on the field directly, 
will overwrite a value in the `translations`-association, if that is indexed by `language-id`.

## Consequences

We will update the DAL to handle translation overwrites as specified above 
and will add test cases to ensure that our implementation adheres to this specification.
