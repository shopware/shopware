---
title: Keep Administration HTTP transports behind a compatibility facade
date: 2026-07-23
area: administration
tags: [administration, axios, compatibility, extensions]
---

## Context

The Administration HTTP client has historically exposed Axios behavior to extensions. Replacing Axios 0.x with Axios 1.x directly would therefore affect interceptor and default configuration, request cancellation, TypeScript types, and test mocks across the extension ecosystem.

Repository consumers are different: repositories expose Shopware's data-access API and do not expose Axios as part of their contract. Requiring extensions to select an Axios version for repository calls would turn an internal implementation change into unnecessary migration work.

## Decision

The Administration exposes a Shopware-owned HTTP client facade while keeping the concrete Axios instances private.

During the transition, direct HTTP requests can select the legacy or new transport with `useAxiosV1`. Interceptors and defaults registered through the facade are applied to both transports, and the facade retains the compatibility needed by existing Axios-based types and test tooling. New code should use Shopware's HTTP client types instead of depending on an Axios instance.

Repository requests always use Axios v1 internally. Repository options cannot select the transport. Any repository incompatibility is fixed centrally in Shopware rather than worked around by each extension.

## Consequences

Extensions can adopt Axios v1 incrementally: repository calls migrate without source changes, while transport-sensitive direct requests retain a temporary fallback. Existing interceptor setup and common test infrastructure continue to work across both transports.

The facade also provides a stable boundary for future HTTP-library upgrades. Version-specific cancellation, errors, configuration, and test compatibility can be normalized in one place instead of requiring coordinated changes throughout Shopware and the extension ecosystem.

Maintaining two transports and transitional Axios compatibility adds temporary complexity. Code that directly depends on Axios-specific behavior must still migrate before the legacy transport and compatibility surface can be removed.

See the [Axios migration guide](../src/Administration/Resources/app/administration/technical-docs/09-security/axios-migration-guide.md) for the supported migration path.
