---
title: Add typescript support for storefront javascript
date: 2022-06-24
area: storefront
tags: [storefront, typescript, javascript]
---

## Context

* We want to add TypeScript support to the Storefront, to make use of all of it's features increasing the overall developer experience, quality and maintainability.
* The main concern is the compatibility to existing Storefront plugins, which have been built in previous versions without TypeScript support.
* TypeScript files need to be compatible with JavaScript files and vice versa, for both the Storefront internally, and also for plugins.

## Decision

* To prevent any breaks in our current Storefront stack, we will add TypeScript language support to the current babel chain, using the preset `@babel/preset-typescript`.
* To prevent any breaks for existing Storefront plugins, we won't replace any publicly used .js files with .ts files, without proper deprecation.

## Consequences

* TypeScript (.ts and .tsx) files are now supported by the Storefront.
* Storefront plugins can now be developed using TypeScript and the actual Storefront JavaScript can be incrementally converted to .ts files from now on.
* TypeScript files and JavaScript files are compatible and can be imported to each other.
