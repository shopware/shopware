---
title: Postpone the stabilization of native setup and the composables to 6.9
date: 2026-11-03
area: administration
tags: [administration, composition-api, native-setup, composables, experimental, extensibility]
---

## Context

Native setup lets extensions write Administration components as `<script setup>` SFCs.
The macros `swDefinePublic()`, `swDefineOverride()`, `useSwPreviousState()`, `useSwProps()` and `useSwContext()` declare what a component exposes and what an override replaces.
The runtime behind it is the Composition API extension system.
Its public parts were annotated `@experimental stableVersion:v6.8.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM`, and native setup was announced as stable with 6.8.0.

The composables that replace the mixins are published as `Shopware.Composables` and through the `shopware:composables` module.
They are annotated `@experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES`.

Native setup still changes from minor to minor.
In the 6.7 releases it gained `defineModel()` support, a parent-facing surface generated from `swDefinePublic()`, and overrides on components that still render through Twig.
A stable API in 6.8.0 would freeze it before it has settled.

## Decision

Native setup and the composables stay experimental until 6.9.0.
The native setup macros in `build/vue-setup-transform/shopware-setup-macros.d.ts` are annotated `@experimental stableVersion:v6.9.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM`.
Every annotation of `ADMIN_COMPOSITION_API_EXTENSION_SYSTEM` that named v6.8.0 now names v6.9.0:

- the Options API to Composition API override shim,
- the `useSnackbar()` composable,
- the container that renders the override components in `sw-admin`.

The composables in `shopware:composables` already target v6.9.0 and keep that marker.

`stableVersion:v6.9.0` is the latest release in which these APIs become stable or are removed.
When they have settled before 6.8.0 is released, we may still stabilize them in 6.8 by removing the `@experimental` annotations.

The other `shopware:*` modules and the extension tooling are not part of this decision.
They keep their target of v6.8.0.

## Consequences

- Extensions that use native setup or the composables get no backwards-compatibility promise in 6.8.
  Signatures and behaviour can change in any minor release without a deprecation cycle.
- Extensions that need a stable API in 6.8 keep using the Options API, Twig blocks and mixins.
- Stabilizing in 6.8 means removing the `@experimental` annotations and updating the status lines of the technical docs.
