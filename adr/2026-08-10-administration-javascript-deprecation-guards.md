---
title: Administration JavaScript deprecation guards
date: 2026-08-10
area: administration
tags: [administration, deprecation, extension-api, feature-flag]
---

## Context

Administration uses JSDoc `@deprecated tag:vX.Y.0` annotations to communicate planned removals. The annotation is useful to IDEs and is consumed by selected ESLint rules, but it does not create runtime behaviour. Consequently an extension can use a deprecated Administration API without receiving a warning during its test suite, and core can keep using it after the next-major feature flag is enabled.

The PHP implementation solves this with `Feature::triggerDeprecationOrThrow()`: it reports legacy use before the major and rejects it once the related major flag is active. Administration needs the same lifecycle for its supported runtime extension APIs.

Not every deprecated Administration member is a supported runtime API. Types, styles, tests, internal template markup and implementation details cannot, or should not, receive a runtime guard. Conversely, component extensions can use base `methods` and `computed` members through `this.$super(...)`; they must not be treated as implementation-only merely because they live in a Vue options object. `data` is inherited as instance state, but is not part of the `$super` mechanism and has no safe generic per-property access hook.

## Decision

### Runtime helper

Add `Shopware.Feature.triggerDeprecationOrThrow(majorFlag, message)` to the Administration feature API.

Before the major flag is active, it emits a development deprecation warning with a useful migration message and call site. When the flag is active, it throws an `Error`.

The helper is used only at the boundary where deprecated functionality is actually consumed. Core must move to the replacement before enabling the major flag; the active-flag error is intended to reveal missed core and extension uses.

```ts
Feature.triggerDeprecationOrThrow(
    'V6_9_0_0',
    'Shopware.Service("example").oldMethod() is deprecated; use newMethod() instead.',
);
```

### Public, detectable APIs

The following API categories require a runtime strategy and enforcement when they are public:

* global `Shopware.*` APIs, registered services, and direct exported functions at their call boundary;
* registered components at mount/creation and deprecated props when the prop is supplied;
* deprecated component `methods` and `computed` members of supported extension targets. The legacy component factory builds `$super` chains for both, so it can perform a guard while resolving `this.$super(member)`; a guard in the member itself additionally covers direct invocation;
* Twig blocks, when the legacy Twig override shim receives the overridden block name. A compatibility shim for a deprecated block must warn or throw there, rather than treating every legacy block as deprecated.

Component events may later be detected from listener vnode properties. They are not part of the initial enforcement scope because that needs a dedicated, tested listener-presence boundary.

For a public legacy data field, prefer moving core to a private backing field and exposing a computed getter/setter facade when runtime compatibility is needed:

```ts
data() {
    return { _legacyField: initialValue };
},
computed: {
    legacyField: {
        get() {
            Feature.triggerDeprecationOrThrow('V6_9_0_0', 'legacyField is deprecated; use replacementField.');
            return this._legacyField;
        },
        set(value) {
            Feature.triggerDeprecationOrThrow('V6_9_0_0', 'legacyField is deprecated; use replacementField.');
            this._legacyField = value;
        },
    },
},
```

An object containing `get` and `set` returned from `data()` is only data; it is not a Vue accessor. The facade does not preserve `$data.legacyField` or object key enumeration, so it is used only where that residual compatibility is not a supported contract.

### Private and static-only symbols

`@private` on the directly attached declaration, and identifiers beginning with `_`, take precedence over `@deprecated` for public-BC enforcement. They are not public extension contracts: no runtime deprecation guard is required, and they may be changed or removed immediately after normal internal-use validation. A symbol marked both private and deprecated should normally be cleaned up as an internal migration rather than kept on a public deprecation lifecycle.

Types, interfaces, SCSS, tests, core-only Twig markup, and unobservable state reads remain static-only. `data`, store state and getters without an explicit facade are static-only as well. Watchers, `provide` and `inject` are static-only unless a future API explicitly defines a reliable use boundary.

### Enforcement

Add an Administration ESLint rule in phases. It must associate a leading `@deprecated tag:vX.Y.0` comment with the declared symbol and validate the corresponding major flag. For a public, runtime-detectable symbol the rule requires a matching `triggerDeprecationOrThrow` call at the appropriate boundary. It skips directly private and underscore-prefixed symbols, and requires an explicit static-only reason for the remaining exceptions.

The initial rule covers direct APIs, public component props, and component methods/computed members. Twig-block enforcement follows with its dedicated shim boundary. Existing annotations are classified before the rule is made blocking; new public deprecations must comply immediately.

## Consequences

Extension developers receive actionable warnings while upgrading and a clear failure in next-major mode, instead of discovering an unsupported call only after the removal. Core's next-major tests also expose its own missed legacy uses.

The rule adds deliberate classification work: reachability through a Vue component does not by itself make a member public, and every public data-field transition must decide whether a computed compatibility facade is worth its remaining limitations.

Private implementation work becomes simpler: private and underscore-prefixed members are not burdened with a public compatibility shim. This relies on `@private` being applied to the actual declaration and on supported extension points being documented/registered as public rather than inferred from technical access.
