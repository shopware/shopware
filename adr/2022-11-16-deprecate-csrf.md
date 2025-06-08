---
title: Deprecate the storefront CSRF implementation
date: 2022-11-16
area: storefront
tags: [csrf, security, storefront]
--- 

## Context

* With browsers evolving and dropping support for older browser in 6.5 we have wide support for SameSite cookies.
* The current CSRF implementation adds a lot of complexity to all forms and ajax calls in the Storefront.
* The CSRF protection does not add a great improvement in security due to the SameSite strategy.

## Decision

* We remove the CSRF protection in favor of SameSite cookies which are used and prevent CSRF attacks already.

## Consequences

* All CSRF implementations in the Storefront will be removed.
