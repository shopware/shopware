---
title: Fix promotion individual code redeemer
date: 2023-07-31
area: core
tags: [core, fix, promotion]
---

## Context

It is currently possible that individual codes will not be redeemed if there is more than one promotion in an Order. 
Additionally, there are many unnecessary database requests fired.

## Decision

We would like to resolve this issue in PromotionIndividualCodeRedeemer and reduce the number of database requests that are fired.
