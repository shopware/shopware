# Drafts and gates

A draft is rejected only when not well-formed. A layout write is rejected when its tree does not resolve.

## The module holds a draft to well-formedness

The module holds a layout write to resolvability. `ContentLayoutWriteValidator` may therefore still reject a draft that previews.

| Gate | Input | What it rejects | Where it runs |
|---|---|---|---|
| Gate on a draft | an unsaved draft | only a tree that is not well-formed | a draft route, which at most reports resolvability |
| [Write gate](README.md#terms-that-are-easy-to-confuse) | a `content_layout` write | a tree that does not fully resolve against the layout's [root context](README.md#glossary), including a tree with an unfilled required property | `ContentLayoutWriteValidator`, on the write |

Why: A draft under construction is legitimately unresolved. A served layout with an unfilled required property always renders empty.

In code:

- `DraftLayoutChecker` keeps only well-formedness errors.
- `ContentLayoutWriteValidator` rejects a `content_layout` write on the binding errors of `LayoutGate::resolvability()`.
- See [Diagnostics/README.md](../../Diagnostics/README.md).

Explicit `map-property` actions validate the requested source immediately, including property opt-in and exact catalogue admission. Rejecting an invalid requested operation with 400 does not require every other mapping in an unbound draft to be resolvable. Source-less diagnosis can still report explicit inline tokens as binding findings; see [mapping checks](../../Diagnostics/README.md#mapping-checks).

## Draft and persisted paths decode and check through the same components

Every mutation operation has a draft route and a persisted route. The draft route is stateless, with no DAL write and no write event. Both paths decode through the one [paired codec](wire-contract.md#the-stored-elements-wire-shape-is-defined-once-by-one-paired-codec) and normalize style through one shared service. One input therefore yields one shape and meets the same codec rule on both paths. Giving one path its own component is a defect even with every test green.

Why: Two decoders for one wire shape handle the same input differently. A draft then previews and fails on save.

Exceptions: The default seeding and attribution reconciliation of the write boundary stay write-only.

In code:

- `DraftLayoutDecoder` decodes with `StoredElementCodec`.
- It shares the `StoredTreeStyleNormalizer` service with `LayoutWriteBoundary`.
- `LayoutMutationController` holds no repository.
- `DraftLayoutStyleParityTest` pins the shared normalizer service.
- See [draft-layout-decoder.md](../../Api/docs/draft-layout-decoder.md).

## Diagnostics report, gates check, and neither rewrites

A diagnose pass reports every malformed element config as a per-element violation in a 200 body. The diagnose pass never throws on a malformed element config. The write gate is a pure reader of the tree that the write already decoded. It never reloads or rewrites that tree. When the write gate rejects the tree, the write returns a 400 and commits nothing.

Why: A diagnose route that throws on the first defect hides the second defect from the editor. A rewriting gate stores changes without telling the editor.

Exceptions: `DraftLayoutDecoder` rejects a structurally unreadable draft with a 400 before diagnostics run.

In code:

- `DraftLayoutDecoder::decodeLintable()` and `LayoutDiagnostics::analyze()` turn a [client defect](README.md#glossary) into an `InvalidConfig` violation.
- `ContentLayoutWriteValidator` checks the tree that `LayoutWriteContext` holds.
- `ContentDiagnoseControllerTest` pins that diagnose answers 200 with the violations in the verdict and throws nothing.
- See [diagnose.md](../../Api/docs/diagnose.md).

## The codec and the constraint descriptor keep separate copies of the wiring rules, and one change tightens both

The codec throws on the first wiring or shape defect in a stated order, and the constraint descriptor reports every one. The codec and the constraint descriptor each keep their own copy of the wiring and shape rules, the write runs both with no third rule set, and one change tightens both together. A value domain that an API schema publishes is implemented once, and a test checks it against the schema's pattern. Draft and persisted request pairs repeat constraints verbatim, not through a static helper.

Why: Under one shared implementation the conformance test compares a rule with itself, unless a published schema is the independent second copy. A descriptor that accepts what the codec rejects lets the codec reject the write with an error the descriptor never reports.

Exceptions: `ConsumerBaseKeyResolver` owns the base-key split. A hand-written tree that bypasses both the codec and the descriptor fails on [read](wire-contract.md#structural-malformation-and-registry-drift-are-different-cases-on-read) or at [render](rendering.md#the-render-validates-the-whole-stored-forest-in-every-mode).

In code:

- `StoredElementCodec` is the codec.
- `StoredTreeConstraints` is the constraint descriptor.
- `ElementIdRule` states the element-id domain that both call.
- `StoredTreeShapeConformanceTest` pins that the codec and the descriptor agree on every payload and names each descriptor-only check.
- `ElementIdSchemaConformanceTest` pins that decode and the published `ContentElementId` pattern accept and reject the same ids, except the empty string.
- See [Layout/Field/README.md](../../Layout/Field/README.md).
