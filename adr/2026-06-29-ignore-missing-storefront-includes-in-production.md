---
title: Ignore missing storefront includes in production
date: 2026-06-29
area: storefront
tags: [storefront, twig, template-rendering, saas]
---

## Context

Storefront templates can include other Twig templates with `sw_include`. When the referenced template does not exist, Twig currently throws an exception. In the storefront this can render an error page for the whole request.

This is useful during development because missing templates usually indicate a broken theme, app, or extension integration. Developers should see the error immediately and fix the template reference.

In production, the same behavior can reduce shop availability. A single missing include from an installed extension can break the full page instead of rendering the rest of the storefront. This is especially relevant for Shopware SaaS, where platform availability and blast-radius reduction are more important than failing the full storefront request for an optional template include.

Twig already supports `ignore missing` for includes. The proposal is to make this behavior the default for `sw_include` in production in the next major version, while keeping strict failures in development.

Affected technical domains:

* Storefront Twig rendering: `sw_include` needs environment-aware default behavior for missing templates.
* Extension and theme development: broken template references should still be visible in development.
* Operations and observability: production shops should stay available, but missing includes must still be detectable.
* Configuration: projects must be able to keep the current strict production behavior if they depend on it.

## Decision

This ADR proposes the following behavior for the next major version:

* In `APP_ENV=prod`, `sw_include` should ignore missing templates by default.
* In `APP_ENV=dev`, `sw_include` should keep throwing an exception for missing templates.
* Projects should have a configuration option to keep strict production behavior and still throw an exception when a storefront include is missing.
* Explicit `ignore missing` usage in templates remains valid and keeps its current meaning.

The default behavior should be equivalent to adding Twig's `ignore missing` option to `sw_include` calls in production only. Development and test environments should stay strict unless configured otherwise.

The configuration should be global for storefront rendering, not a per-template migration requirement. This avoids requiring all existing templates to be changed and makes the new production behavior predictable.

Example behavior:

```twig
{% sw_include '@Storefront/storefront/component/example.html.twig' %}
```

Proposed result:

* `APP_ENV=dev`: throw an exception when the template is missing.
* `APP_ENV=prod`: render without the missing include by default.
* `APP_ENV=prod` with strict include configuration enabled: throw an exception when the template is missing.

Possible configuration shape:

```yaml
storefront:
    twig:
        strict_missing_includes: true
```

The exact configuration name is not part of this ADR and should be finalized during implementation.

## Consequences

Production storefronts become more resilient against broken optional template includes from extensions, apps, or themes. A missing include no longer needs to break the whole shop by default.

Extension and theme developers still receive immediate feedback in development because `APP_ENV=dev` keeps throwing exceptions. This keeps the local developer experience strict and prevents missing templates from being silently ignored while building or testing integrations.

Projects that intentionally rely on strict production failures can opt in to the current behavior through configuration. This is important for self-hosted projects, CI-like production checks, and teams that prefer fail-fast behavior over partial storefront rendering.

The production default can hide visible storefront fragments when a template is missing. Therefore the implementation should make missing includes observable, for example by logging the missing template with enough context to identify the caller and affected sales channel.

This is a behavioral change and should only become the default in the next major version. It needs release documentation and migration guidance explaining the new default and the strict production configuration option.
