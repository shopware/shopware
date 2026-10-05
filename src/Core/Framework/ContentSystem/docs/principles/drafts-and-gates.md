# Drafts and gates

This area concerns the standard the module applies to an unsaved draft and to a layout write, and the components that both paths share. The model is two standards, well-formedness for a draft and resolvability for a layout write, checked through shared components.

## The module holds a draft to well-formedness

The module holds a layout write to resolvability. `ContentLayoutWriteValidator` may therefore still reject a draft that previews.

| Gate | Input | What it rejects | Where it runs |
|---|---|---|---|
| Gate on a draft | an unsaved draft | only a tree that is not well-formed | a draft route, which at most reports resolvability |
| Write gate | a `content_layout` write | a tree that does not fully resolve against the layout's root context, including a tree with an unfilled required property | `ContentLayoutWriteValidator`, on the write |

Why: A draft under construction is legitimately unresolved. A served layout with an unfilled required property renders permanent emptiness.

In code:

- `DraftLayoutChecker` keeps only intrinsic errors.
- `ContentLayoutWriteValidator` rejects a `content_layout` write on the binding errors of `LayoutGate::resolvability()`.
- See [Diagnostics/README.md](../../Diagnostics/README.md).

## Draft and persisted paths decode and check through the same components

Every mutation operation has a draft route and a persisted route. The draft route is stateless, with no DAL write and no write event. Both paths decode through the one [paired codec](wire-contract.md#the-stored-elements-wire-shape-is-defined-once-by-one-paired-codec) and normalize style through one shared service. One input therefore yields one shape and meets the same codec rule on both paths. Giving one path its own component is a defect even with every test green.

Why: Two decoders for one wire shape diverge silently. The divergence shows only as a draft that previews and then fails to save.

Exceptions: The default seeding and attribution reconciliation of the write boundary stay write-only.

In code:

- `DraftLayoutDecoder` decodes with `StoredElementCodec`.
- It shares the `StoredTreeStyleNormalizer` service with `LayoutWriteBoundary`.
- `LayoutMutationController` holds no repository.
- `DraftLayoutStyleParityTest` pins the shared normalizer service.
- See [draft-layout-decoder.md](../../Api/docs/draft-layout-decoder.md).

## Diagnostics report, gates check, and neither rewrites

A diagnose pass reports every malformed element config as a per-element violation in a 200 body. The diagnose pass never throws on a malformed element config. The write gate is a pure reader of the tree that the write already decoded. It never reloads or rewrites that tree. When the write gate rejects the tree, the write returns a 400 and commits nothing.

Why: A diagnose route that throws on the first defect hides the second defect from the editor. A write gate that rewrites the tree lets an unannounced change through.

Exceptions: `DraftLayoutDecoder` rejects a structurally unreadable draft with a 400 before diagnostics run.

In code:

- `DraftLayoutDecoder::decodeLintable()` and `LayoutDiagnostics::analyze()` turn a client defect into an `InvalidConfig` violation.
- `ContentLayoutWriteValidator` checks the tree that `LayoutWriteContext` holds.
- `ContentDiagnoseControllerTest` pins that diagnose answers 200 with the violations in the verdict and throws nothing.
- See [diagnose.md](../../Api/docs/diagnose.md).

## The codec and the constraint descriptor keep separate copies of the wiring rules, and one change tightens both

The codec throws on the first wiring or shape defect in a stated order. The constraint descriptor reports every wiring or shape defect. The codec and the constraint descriptor each keep their own copy of the rules. The write runs both with no third rule set. One change tightens the codec and the constraint descriptor together. `StoredTreeShapeConformanceTest` pins where the codec and the descriptor agree and names the descriptor-only checks. Draft and persisted request pairs repeat constraints verbatim, not through a static helper.

Why: Sharing one implementation hides the divergence that the conformance test exists to catch. A codec that rejects what the descriptor accepts persists payloads that it cannot read back.

Exceptions: `ConsumerBaseKeyResolver` owns the base-key split. A hand-written tree stored past both the codec and the descriptor fails on [read](wire-contract.md#structural-malformation-and-registry-drift-are-different-cases-on-read) or at [render](rendering.md#the-render-validates-the-whole-stored-forest-in-every-mode).

In code:

- `StoredElementCodec` is the codec.
- `StoredTreeConstraints` is the constraint descriptor.
- `StoredTreeShapeConformanceTest` pins that the codec and the descriptor agree on every payload and names each descriptor-only check.
- See [Layout/Field/README.md](../../Layout/Field/README.md).
