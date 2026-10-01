# Principles

## Terms that are easy to confuse

| Pair | What they share | The one axis on which they differ | Governed by |
|---|---|---|---|
| stored element / rendered element | Both describe one element of a layout tree. | `StoredElement` is what a layout persists, with wiring and wrapped values. `RenderedElement` is what a page serves, with raw values and no wiring. | [stored-model.md](stored-model.md) |
| present null / absent key | Neither gives the property a non-null value. | [values.md](values.md#a-present-null-and-an-absent-key-are-different-states) | [values.md](values.md) |
| FULL / SKELETON | Both run the one render path and produce the same structure. | FULL resolves data. SKELETON switches off placeholder substitution and data resolution. | [rendering.md](rendering.md) |
| write gate / write boundary | Both sit on the DAL write path of a layout tree. | [write-admission.md](write-admission.md#every-layout-write-passes-through-the-write-boundary-a-single-choke-point), [drafts-and-gates.md](drafts-and-gates.md#the-module-holds-a-draft-to-well-formedness) | [write-admission.md](write-admission.md), [drafts-and-gates.md](drafts-and-gates.md) |
| client defect / internal fault | Both are defects that `ContentSystemException` throws. | [failure-and-loss.md](failure-and-loss.md#the-cause-of-a-defect-sets-its-classification-and-http-status-is-a-separate-axis) | [failure-and-loss.md](failure-and-loss.md) |

## Glossary

- reject: a gate, validator, decoder or constructor does not accept an input and returns an error or throws.
- throw: an exception leaves a method.
- fail: a process such as install, update or a build ends with an error.
- check: a component or a reader examines something for a condition.
- prove resolvable: [drafts-and-gates.md](drafts-and-gates.md#the-module-holds-a-draft-to-well-formedness).
- pin, pinned by: the test that asserts a rule.
- skip and log: [failure-and-loss.md](failure-and-loss.md#a-declaration-or-registration-defect-fails-the-build-or-the-load-never-a-request).
- exception: a designed exception to a rule, present in code.
- the module: the Content System under `src/Core/Framework/ContentSystem/`.
- present null, authored null, absent: [values.md](values.md#a-present-null-and-an-absent-key-are-different-states).
- client defect: [failure-and-loss.md](failure-and-loss.md#the-cause-of-a-defect-sets-its-classification-and-http-status-is-a-separate-axis).
- write boundary: [write-admission.md](write-admission.md#every-layout-write-passes-through-the-write-boundary-a-single-choke-point).
- write gate: [drafts-and-gates.md](drafts-and-gates.md#the-module-holds-a-draft-to-well-formedness).
- conformance validator: [drafts-and-gates.md](drafts-and-gates.md#the-codec-and-the-constraint-descriptor-keep-separate-copies-of-the-wiring-rules-and-one-change-tightens-both).
- constraint descriptor: [write-admission.md](write-admission.md#no-dal-write-can-bypass-the-constraint-descriptor).
- loss, dropped, orphaned: [failure-and-loss.md](failure-and-loss.md#the-module-drops-nothing-silently-and-reports-every-loss).

In this module, "context" is the per-element delivery map. In the platform, `Context` is the Shopware context object.

## Module map

```mermaid
graph LR
    W(["Layout write<br/>Admin API, Sync API, DAL"])
    WB["Write boundary<br/>LayoutWriteBoundary::apply()"]
    WG{{"Write gate<br/>LayoutGate, StoredTreeConstraints"}}
    SF[("Stored forest<br/>StoredElement")]
    W --> WB --> WG -- "admitted" --> SF

    RP["Render path: ContentPipeline::load(), see data-flow.md"]

    SF --> RP
    RP -- "rendered forest" --> SR(["Store API response<br/>RenderedElement"])
    SF -. "stored shape" .-> AR(["Admin API body<br/>StoredElement"])

    classDef wire fill:#e3f2fd,stroke:#1565c0,stroke-width:1px,color:#0d47a1
    classDef step fill:#e8f5e9,stroke:#2e7d32,stroke-width:2px,color:#1b5e20
    classDef gate fill:#fdecea,stroke:#c62828,stroke-width:2px,color:#b71c1c
    classDef data fill:#f3e5f5,stroke:#6a1b9a,stroke-width:2px,color:#4a148c
    class W,SR,AR wire
    class WB,RP step
    class WG gate
    class SF data
```

The render path's steps are in [data-flow.md](../data-flow.md).

## Index

### By task

- A layout write is rejected: [write-admission.md](write-admission.md), [drafts-and-gates.md](drafts-and-gates.md), [values.md](values.md)
- A rendered value is wrong: [rendering.md](rendering.md), [data-loading.md](data-loading.md), [context-wiring.md](context-wiring.md)
- A client shows the wrong error: [failure-and-loss.md](failure-and-loss.md)
- A listener changed the tree: [rendering.md](rendering.md), [extension-surface.md](extension-surface.md)
- A preview differs from the storefront or rejects a draft: [preview.md](preview.md)

### By area

- [failure-and-loss.md](failure-and-loss.md): fail fast and report every loss
- [values.md](values.md): the server owns every value rule
- [stored-model.md](stored-model.md): the stored element and its one conversion
- [type-declarations.md](type-declarations.md): what a type declaration may state
- [write-admission.md](write-admission.md): the write boundary and the order of its passes
- [drafts-and-gates.md](drafts-and-gates.md): well-formedness for a draft and resolvability for a write
- [mutation.md](mutation.md): a mutation operation as a pure tree transform
- [data-loading.md](data-loading.md): loaders as consumers of typed inputs
- [context-wiring.md](context-wiring.md): context between adjacent elements
- [rendering.md](rendering.md): the render's final check and the two tree-replacement events
- [preview.md](preview.md): preview as a second entry into the render path
- [wire-contract.md](wire-contract.md): one paired codec and one rendered forest per format
- [extension-surface.md](extension-surface.md): the bounded extension surface
- [clients.md](clients.md): the administration type and the storefront's shared partial

## Changing a rule

The pinning test is the enforcing class's test, unless the In code list names another. It changes in the same commit as the rule.
