---
title: Mapping of product area
date: 2022-09-28
area: product-operations
tags: [workflow]
---

## Context

We have a lot of teams working on different parts of the Shopware 6 platform.
We want to have a clear mapping of the teams to the source code, so that we can easily assign the right area to a ticket.
This allows us also to map automatically errors reported in our SaaS application to the right area.

## Decision

We decided to add a `@package <area>` annotation to all files in the `src` and `tests` directory of the `platform`, `rufus` and `commercial` repository.
This annotation will be used to map the files to the product areas.

The areas are:

- admin
- storefront
- core
- inventory
- checkout
- content
- customer-order
- services-settings
- buyers-experience

## Consequences

We will add a PHP-doc/JavaScript comment to any file with `@package <area>`
