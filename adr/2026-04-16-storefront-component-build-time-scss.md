---
title: Build-time SCSS compilation for Storefront components
date: 2026-04-20
area: storefront
tags: [storefront, scss, vite, components, theme-compiler]
---

## Context

* The Storefront component system introduces theme-agnostic Twig UX components that can be contributed by the Storefront, plugins, and apps.
* The existing SCSS compilation pipeline runs inside PHP (`ThemeCompiler` via `scssphp/scssphp`) as part of `theme:compile`. It was designed for the monolithic per-theme stylesheet (`all.css`) and is tightly coupled to the theme lifecycle.
* Running component SCSS through the PHP compiler would mean component styles are recompiled on every `theme:compile`, even when the component source has not changed.
* The PHP compiler does not have access to the same Sass load paths, Node.js ecosystem packages, or modern Sass features that are available in the JS build toolchain.
* Compiling component SCSS inside the PHP compiler would also require the compiler to know which component files belong to which bundle, adding more complexity to the theme system.
* For apps, component source files are only available inside the app's zip archive. Resolving them through the PHP compiler would require downloading and unpacking the zip on every `theme:compile` just to discover and process SCSS files.

## Decision

* Component SCSS is compiled at **build time** by Vite (the same toolchain already used to compile component JavaScript), not by the PHP theme compiler.
* Each bundle's `Resources/views/components/**/*.scss` files are compiled to individual CSS files and placed in `Resources/public/storefront/components/` alongside their JS counterparts.
* The compiled CSS files are theme-agnostic and are published through Shopware's normal asset flow (`assets:install` to `public/bundles/<bundle>/storefront/components`). Theme compile reads bundle-local Vite build meta files and provides CSS information for components in the import map saved in the theme runtime config.
* Theme customisation that previously relied on injecting SCSS variables at compile time (e.g. `$sw-color-brand-primary`) is replaced by **native CSS custom properties** (`var(--sw-color-brand-primary)`). The current active theme's configuration is written as a `<style>` block of `--sw-*` custom properties into the storefront page template at render time.

## Consequences

* **Component SCSS cannot use SCSS theme variables** (`$sw-*`). Components that need runtime-configurable values must use CSS custom properties (`var(--sw-*)`). SCSS abstracts from Bootstrap or the Shopware skin layer (mixins, functions, fixed design tokens) remain fully available.
* **Component SCSS cannot use runtime feature-flag state**. Feature flags are runtime information, while component SCSS is compiled at build time. Conditional styling that depends on feature flags must be handled at runtime (for example via markup/classes/data attributes or JavaScript), not via SCSS feature checks.
* Component CSS is compiled once per deployment, not on every `theme:compile`. A build step (`composer build:js:storefront` or the individual component build) must run whenever component SCSS source changes.
* Developers adding a new bundle with components must ensure their component stylesheets do not depend on SCSS variables that are only resolved at theme-compile time.
* Extensions (plugins and apps) must ship the pre-compiled CSS files as part of their release artifact. The compiled output from `Resources/public/storefront/components/` must be included in the extension files so that they can be copied without requiring a build step on the merchant's server.
