---
title: Remove static analysis with psalm
date: 2022-05-12
area: core
tags: [phpstan, psalm, static-analyse]
---

## Context
Currently, we are running static analysis over the php code with both `phpstan` and `psalm`.
This slows down our pipeline and may lead to weird effects where `phpstan` and `psalm` report errors that are incompatible with each other. 

## Decision
There is not much need anymore to run both tools, as they pretty much converged to a common feature set.
This was different when we started with shopware 6 where both tools had some different features, but most of the differences are gone by now.
Therefore, we won't run both tools anymore in the CI.

We decided to stick with `phpstan` and remove `psalm` because:
* It's easier to write custom `phpstan` rules than to extend `psalm`
* We already have custom `phpstan` rules
* There are more extension for `phpstan`, e.g. for `symfony` or `phpunit`

## Consequences
`psalm` will be completely removed from the repository and the CI.
