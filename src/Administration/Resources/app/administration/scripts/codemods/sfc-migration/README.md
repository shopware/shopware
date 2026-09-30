# SFC Migration Codemod

Converts Options API Administration components (`index.js` + `*.html.twig`) into native setup SFCs
(`<component-name>.vue` with `swDefinePublic`, see
`technical-docs/03-extensibility/07-native-setup-authoring.md`).

## Usage

`--write` creates validated Vue drafts and leaves the legacy entry point and Twig file untouched.
Replacing an entry point is a separate, explicit operation; Twig files are retained for a later
human-reviewed cleanup.

`--write` requires the target directory to be clean in git (`git status --porcelain -- <path>`
empty): a run is undone with `git checkout`, which only works when nothing else was uncommitted.
A dirty target aborts the run before anything is written. Outside a git working tree the check does
not apply. Dry runs never check.

```bash
# Dry run (default): prints a per-component report, writes nothing
npm run codemod:sfc-migration -- src/app/component/base

# Write validated Vue drafts only
npm run codemod:sfc-migration -- src/app/component/base --write

# Replace eligible legacy entry points as a separate explicit step (Twig is retained)
npm run codemod:sfc-migration -- src/app/component/base --write --replace-originals
```

From the project root the same run is `composer admin:codemod:sfc-migration -- <path> [flags]`. The
`--` is not optional there: without it Composer consumes `--write` itself and the run silently stays
a dry run.

## Outcomes

| Outcome            | Meaning                                          | `--write` behavior                                                                                                                                                     |
| ------------------ | ------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `full`             | Everything converted, output validated           | Writes `<dir>/<component-name>.vue`; with `--replace-originals`, an unambiguous plain registration may also replace `index.js` with a re-export shim. Twig is retained |
| `partial`          | Converted with `// TODO(sfc-migration)` comments | Writes the `.vue` draft only; `index.js` + twig stay untouched, the component keeps running as before                                                                  |
| `skipped`          | Structural blocker                               | Writes nothing; the report names the reason                                                                                                                            |
| `already-migrated` | A `.vue` with the component's name exists        | Writes nothing; the reason says whether it is an earlier draft, a half-migration, or a file this codemod never wrote                                                   |
| `error`            | The conversion or a write threw                  | Reports what ended up on disk; the run continues and exits `1`                                                                                                         |

Every generated SFC must pass the real build transform (`build/vue-setup-transform`) plus Vue's own
`compileScript`/`compileTemplate` before it is written — a non-compiling file is never produced.
That gate proves the output _compiles_, not that it _behaves the same_, so shapes that would compile
into different behaviour are refused separately (see below).

A component whose entry point was explicitly replaced is never rediscovered: its `index.js` is a
re-export, which the discovery pass does not recognise as a component. Draft-only re-runs report
the existing draft and leave it untouched.

### TODO modes

A `TODO(sfc-migration)` in a draft says which of two things it asks of its reader, so neither has to be
guessed from the wording:

| Marker                        | Meaning                                                                                                                                                                  |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `TODO(sfc-migration) FIX:`    | The emitted code does not run as it stands — the reader writes what the codemod refused to guess. The comment names what is left as authored and what to replace it with |
| `TODO(sfc-migration) VERIFY:` | The conversion is complete and runs; what it cannot prove is that it behaves the same. The comment says why, and lists what to check                                     |
| `TODO(sfc-migration):`        | Not classified into either mode yet                                                                                                                                      |

### Write failures

Each component's writes are guarded on their own. A failure reports that component as `error` and
the run continues, so the report still covers everything else. Twig is never deleted. Recovery is
`git checkout` on the target directory — which is exactly what the clean-tree requirement above
buys, so the writes themselves are plain `fs.writeFileSync` calls.

## Registration classes

Independent of the outcome, every component is classified by the `Component.*` call its directory is
registered through (`component-source-model.ts`). The CLI carries the class per component plus a split
summary line. Scan failures are retained as structured diagnostics and do not stop later components
from being reported.

The class decides how far a component is migrated: only a plain `Component.register` takes the
destructive path, because only its template stands on its own.

| Class          | Meaning                                                                                                                   | `--write` behaviour                                                                                             |
| -------------- | ------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `register`     | `Component.register('name', () => import('./dir'))` — the plain case                                                      | Writes a draft with `--write`; only the unambiguous full path may replace `index.js` with `--replace-originals` |
| `extend`       | `Component.extend('child', 'parent', () => import('./dir'))` — child of another component                                 | Skipped; it renders against bindings its parent declares                                                        |
| `override`     | A directory registered through `Component.override('name', () => import('./dir'))` (none today, so the column is omitted) | Skipped; its template patches another component's markup                                                        |
| `unregistered` | No registration resolves to the directory (helpers, dynamically registered or dead components)                            | Draft only — `index.js` and Twig are kept                                                                       |

Inline `Component.override('name', { … })` configs own no directory and never reach the component
discovery. They are counted separately and reported as one info line — reporting only, the codemod
cannot convert them.

## Component names

The name a component is generated under comes from its registration, not from its directory — that
is what unlocks the CMS blocks/elements (`blocks/text/text/component` registers `sw-cms-block-text`)
and the `page/index` pages. Directories no registration resolves to keep their basename. The name
becomes the `.vue` filename, from which the build transform derives the runtime component name.

Two gates guard the derivation: a name the directory does not carry must be confirmed by the
template filename (`sw-cms-block-text.html.twig`), and a name registered for more than one directory
is never used. Either mismatch skips the component instead of guessing.

Registrations are collected from the whole Administration `src/` whenever the target is contained by it,
so running the codemod on a single module still resolves names registered in a parent directory.
For targets outside `src/` the scan root is the target itself.

## Mixins

A `mixins` entry is converted when `composables/` has a descriptor for the mixin's registered name
(both `Mixin.getByName('x')` and the bare `mixins: ['x']` form resolve through it). The descriptor
names the composable to import and the members it answers, and those members become entries in the
same `ctx.bindings` map a component's own members use, so `this.<member>` rewrites need nothing extra.
Only members the script or the template actually reads are destructured.

A descriptor is data, not code. Its fields:

| Field                                                | Answers                                                                                               |
| ---------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `id`, `mixinNames`                                   | Which registered mixin names does this cover, and what is it called in a message?                     |
| `import`                                             | Which composable replaces it? (a default export, so `name` is the local binding)                      |
| `members`                                            | Which `this.<member>` does it answer, and as what — `ref` (`.value` on rewrite), `value` or `method`? |
| `internallyReferencedMembers`                        | Which of those does the composable call itself, so an override could no longer reach it?              |
| `unmappedMembers`                                    | Which members did the mixin put on `this` that the composable does not return?                        |
| `emits`, `propArgs`, `callbackArgs`, `providedProps` | What did the mixin take from — and give to — its host instance? (see below)                           |
| `scaffold`                                           | Was it a controller rather than a helper, so its output is a draft? (see below)                       |

Conversion is all-or-nothing per component: one mixin without a descriptor keeps the whole component
on the Options API, because a half-converted `mixins` array has no safe meaning. Five more cases keep
it there even though a descriptor exists, all of them places where the composable is not a drop-in for
the mixin's `this` semantics:

- `component redefines 'x' from the 'y' mixin` — its own version wins today, and after the migration
  both would want the same binding name;
- `component redefines 'x', which the 'y' composable calls internally`, where the override would
  silently stop taking effect;
- `'x' is read but the 'y' composable does not provide it` — a computed the composable inlines, unless
  the component declares its own member of that name, which shadowed the mixin's anyway;
- `'x' is assigned to, but the 'y' composable returns it as a constant`, which was a write to the
  instance proxy and has no equivalent against a `const`;
- `'x' is read in the template and its binding name is already taken`. A script-only read is fixed by
  renaming the binding (`const { salutation: salutation$1 } = useSalutation()`), but the template
  cannot be rewritten.

A rename is emitted, not refused, but the generated name is the codemod's own and it costs the member
its `swDefinePublic` entry. So the draft carries a `VERIFY` TODO directly above the destructure, and
the outcome is `partial` until a reader has picked a name:

```js
// TODO(sfc-migration) VERIFY: 'salutation' was renamed to 'salutation$1' — its name is already taken by another binding
// The draft runs as emitted; a renamed member stays out of swDefinePublic, so rename it and its uses to have it public or prettier
const { salutation: salutation$1 } = useSalutation();
```

### Instance dependencies

A mixin that reaches into its host — `$emit`, a prop it read, a method it expected the host to define —
declares that in its descriptor, and the composable takes all of it as one options object:

```js
const emit = defineEmits(['media-folder-change']);

const { selectedItems } = useMediaGridListener({
    onFolderChange: (...args) => emit('media-folder-change', ...args),
    selectableItems: () => selectableItems.value,
});
```

- `emits` names the events the mixin emitted. The codemod merges them into `defineEmits` and passes
  `emit` through callbacks named after the intent, so the composable carries no event strings. A
  component whose own `emits` option is not a plain list of names is refused — the object form's
  validators cannot be merged into.
- `propArgs` names the props the mixin read. They are passed as `() => props.<name>` getters so the
  read stays reactive; a component that does not declare one is refused, because the prop came from the
  mixin's own `props` option and nothing would supply it afterwards.
- `callbackArgs` names the members the mixin expected the host to define. The codemod passes the
  component's own member — state and props as a getter, a method as a forwarding call — and refuses a
  component that defines none, unless the descriptor marks the argument optional
  (`component does not define 'getList', which the 'listing' composable calls`, or
  `'getList' is declared in a shape that cannot be handed to the 'listing' composable` when
  classification dropped it).

Every argument defers its read, because the composable call is assembled above the member sections it
points at.

Travelling the other way, `providedProps` names the props the mixin _declared_, which every component
using it inherited. A composable cannot declare props, so the codemod merges them into the component's
own `defineProps` literal — a component prop of the same name wins, mirroring Vue's option merge, and a
`props` option that is not a plain object literal is refused. Unlike the dependencies above, these are
merged for every declared mixin, whether its composable ends up being called or not.

### Scaffolds

Some mixins were abstract controllers rather than helpers: they owned the state a component worked
against — their own, or a prop they wrote to — plus a lifecycle, and drove a member the component was
expected to implement. Wiring one up is mechanical, but proving the result still behaves the same is
not, so a descriptor with a `scaffold` field always produces a `partial` draft, never a `full`
migration. Its `checks` lead the draft as one summary TODO block in `VERIFY` mode, which is also what
makes the outcome `partial`:

```js
// TODO(sfc-migration) VERIFY: useListing() replaces the 'listing' mixin
// Nothing is missing from the draft; what the codemod cannot decide is whether it behaves the same — check:
// - getList() is passed to useListing() and still resolves everything it reads and writes
// - the initial load runs on mounted now, one hook later than the mixin loaded it
// - route parameter handling, which the composable owns from here on
// - these were routed into the composable options instead of staying state: limit, sortBy
```

- `iocMember` is the member the mixin called on its host. It is handed over as a callback like any
  other, and it is what marks the mixin as owning a lifecycle: such a composable is called even when
  the component reads nothing back from it. A scaffold without one is dropped like any other descriptor
  nothing reads.
- `configKeys` are state keys a component set in its own `data()` purely to configure the mixin. They
  move into the composable's options object instead of staying local refs, and stop counting as the
  component redefining a mixin member.

Two mixins are scaffolded today. `listing` owns its state and drives `getList`. `cms-element` owns no
state of its own: it writes to the `element` prop, which is the slot the `cmsPage` store shares with
the rest of the CMS editor, so the codemod emits `useCmsElementDeprecated` — faithful to that
in-place mutation, and deprecated in its name because `useCmsElement` routes the same writes through
the store. The codemod never emits the clean one; that migration is a human's call.

`cms-element` also declared `cms-state` as its own mixin, so a component naming only `cms-element` read
the CMS editor state through it. Its descriptor therefore answers those members as well, which is why
both descriptors share one member list.

## What is skipped on purpose

`Component.extend` children, `Component.override` registrations, `this.$super`/`this.$parent`,
`render()` components, root-level option spreads, components whose `name` option differs from their
component name, components whose registered name neither the directory nor the template filename
confirms, and mixins no composable covers (see above).

Two more are refused because base output cannot express them, and because the markup they would
produce compiles while rendering something different:

- a `{% block %}` wrapping a named slot — `<sw-block>` renders only its default slot, so the slot
  would be re-parented onto it and its content dropped;
- `{% parent %}` — meaningful only in an override, where the codemod does not write yet.

These need structural decisions a codemod should not guess. Everything else that is not understood
becomes a TODO comment in a draft instead of a silent conversion — including a `this.<member>` whose
name a local binding shadows. Module-level code is retained in a normal script block so it still runs
once per module load.

## Structure

Each file answers exactly one question:

| File                                      | Answers                                                                                                                                                                                                                                        |
| ----------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `run-sfc-migration.ts`                    | How does a batch run work? CLI entry, clean-tree guard, discovery, file writes, report                                                                                                                                                         |
| `component-source-model.ts`               | Which source files, registrations, and exact Twig binding belong together? The one structural read of the tree                                                                                                                                 |
| `convert-component.ts`                    | What happens to one component? The pipeline: template + script transform → prettier → validation gate                                                                                                                                          |
| `transform-template.ts`                   | How does twig become a Vue template? (`{% block %}` → `<sw-block>`, comments, the `{% parent %}` and leftover-twig gates)                                                                                                                      |
| `template-ast.ts`                         | What does a converted template look like? Shared `@vue/compiler-dom` parse and the `<sw-block>` shape predicate                                                                                                                                |
| `assert-block-slots.ts`                   | Does a converted block swallow content? Named-slot children of `<sw-block>`                                                                                                                                                                    |
| `assert-single-root.ts`                   | Did the conversion cost the component its single root? Root tally before vs. after the blocks                                                                                                                                                  |
| `move-root-comments.ts`                   | Where does a root Twig comment go? Outside `<template>`, so the note stays in the SFC without becoming a rendered root                                                                                                                          |
| `normalize-cross-block-conditionals.ts`   | How does a `v-if` chain survive a block boundary? Guard branches for `v-else`/`v-else-if` the conversion orphaned                                                                                                                              |
| `transform-script.ts`                     | In what order is the `<script setup>` assembled? Orchestrates parse → collect → rewrite → render                                                                                                                                               |
| `option-handlers.ts`                      | How is each top-level option handled? One handler per option (`props`, `data`, `watch`, …)                                                                                                                                                     |
| `rewrite-this.ts`                         | Where does each `this.x` reference go? The rewrite pass, aware of both `this` binding and lexical scope                                                                                                                                        |
| `tables.ts`                               | What converts to what? All conversion tables — the extension surface                                                                                                                                                                           |
| `composables/`                            | Which mixin has a composable, and which `this.<member>` does it answer? `descriptors/index.ts` assembles the registry, `types.ts` holds the descriptor shape, `descriptors/<id>.ts` one descriptor per mixin, `index.ts` the queries over them |
| `validate.ts`                             | Is the output safe to write? Real build transform + Vue compiler round-trip                                                                                                                                                                    |
| `ast.ts`                                  | Shared transform context and generic AST/text helpers — no conversion policy                                                                                                                                                                   |
| `sfc-migration.spec.ts` + `__fixtures__/` | What does one component convert into? A snapshot of every fixture through the full pipeline                                                                                                                                                    |
| `mixin-composables.spec.ts`               | Which mixin declarations resolve, and which cases keep the Options API?                                                                                                                                                                        |
| `run-sfc-migration.spec.ts`               | What does the runner do to files? CLI exit codes, draft/replacement modes, name derivation, and existing-`.vue` behaviour                                                                                                                      |
| `spec-helpers.ts`                         | Helpers shared by the specs: throwaway component trees, and the one way a fixture reaches the pipeline                                                                                                                                         |
| `runtime-equivalence-*.ts`                | Does a supported shape execute equivalently, or stay conservative?                                                                                                                                                                             |

## Extending the codemod

The conversion rules are data tables plus one handler per option:

- `tables.ts` — `INSTANCE_PROPS` (one entry per `this.$xyz` rewrite: replacement + required
  helper/import) and the `OPTION_TIERS` (`skip` / `todo`) assignment for top-level options.
- `option-handlers.ts` — `OPTION_HANDLERS`, one small handler per supported option (`props`,
  `data`, `computed`, `watch`, …). Promoting a feature means dropping its key from `OPTION_TIERS`
  and adding a handler — never both, because the tier is read first; the classification loop, the
  `this.` rewrite pass (`rewrite-this.ts`) and the render pass (`transform-script.ts`) stay untouched.
  A handler that creates instance members also has to teach `collectOwnMemberNames()` about them, or
  the mixin override guard stops seeing what it compares against — the ownership-superset invariant
  in `mixin-composables.spec.ts` fails when it does not.
- `composables/` — `COMPOSABLE_DESCRIPTORS`, assembled in `composables/descriptors/index.ts` from one
  file per mixin that has a composable. Supporting another mixin means writing the composable and adding
  its descriptor; `resolveMixins()` and the render pass stay untouched. A new descriptor needs, in this order:
  the composable under `src/app/composables/` (a default export, `@private` and
  `@experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES` — the layer is experimental
  until the next major, so a composable's shape may still change — with a cross-reference comment on
  the mixin, which stays in place); the descriptor itself in
  `composables/descriptors/<id>.ts`, named after its own `id` (a default export) and registered in
  `composables/descriptors/index.ts`, its member kinds read off the mixin's `computed`, `methods` and `data`,
  everything the mixin called on itself in `internallyReferencedMembers`, and everything it kept to
  itself in `unmappedMembers`; a fixture per new behaviour and per new refusal; and a named assertion
  in `mixin-composables.spec.ts`. The registry invariants there hold the descriptor to its own shape
  and to its filename, so a typo in a member name or a file nothing imports fails as a test rather
  than as output.
- New conversions are covered by dropping a fixture folder into `__fixtures__/` —
  `sfc-migration.spec.ts` snapshots every fixture automatically and runs the full validation gate.
