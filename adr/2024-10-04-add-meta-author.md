---
title: Add meta author configuration and use it in frontend
date: 2024-10-04
area: storefront
tags: [administration, storefront]
---

## Context

Currently we don't have any `<meta>` author.

## Decision

We add a configuration for a default author and use this if it is not overwritten.

## Consequences

All pages get a `<meta>` author.
