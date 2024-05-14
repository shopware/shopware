---
title: Replace drop-shadow with box-shadow
date: 2022-21-11
area: storefront
tags: [safari, performance, storefront]
---

## Context
Safari has drastic performance issues with drop-shadow.

## Decision
Changing it to box-shadow solves all the performance issues.

## Consequences
The design and optic of the drop-shadow is slightly different. It is not as perfect as before. But it looks almost the same
and is much faster.
