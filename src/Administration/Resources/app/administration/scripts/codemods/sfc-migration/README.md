# SFC Migration Codemod

Converts Options API Administration components (`index.js` + `*.html.twig`) into native setup SFCs
(`<component-name>.vue` with `swDefinePublic`, see
`technical-docs/03-extensibility/07-native-setup-authoring.md`).

## Usage

`--write` rewrites and deletes files in place, with no confirmation and no backup. Commit first —
`git` is the only undo.

```bash
# Dry run (default): prints a per-component report, writes nothing
npm run codemod:sfc-migration -- src/app/component/base

# Apply the migration
npm run codemod:sfc-migration -- src/app/component/base --write

# Aggregate a dry run into a markdown report (skip/TODO reasons with component counts),
# written to <repo root>/SFC-CODEMOD-SKIP-ANALYSIS.md by default
npm run codemod:sfc-migration:analyze -- src/ [--out <file>]
```

## Outcomes

| Outcome | Meaning | `--write` behavior |
| --- | --- | --- |
| `full` | Everything converted, output validated | Writes `<dir>/<component-name>.vue`, shrinks `index.js` to a re-export shim, deletes the twig (kept if another file still imports it) |
| `partial` | Converted with `// TODO(sfc-migration)` comments | Writes the `.vue` draft only; `index.js` + twig stay untouched, the component keeps running as before |
| `skipped` | Structural blocker | Writes nothing; the report names the reason |
| `already-migrated` | A `.vue` with the component's name exists | Writes nothing; the reason says whether it is an earlier draft, a half-migration, or a file this codemod never wrote |
| `error` | The conversion or a write threw | Reports what ended up on disk; the run continues and exits `1` |

Every generated SFC must pass the real build transform (`build/vue-setup-transform`) plus Vue's own
`compileScript`/`compileTemplate` before it is written — a non-compiling file is never produced.
That gate proves the output *compiles*, not that it *behaves the same*, so shapes that would compile
into different behaviour are refused separately (see below).

A completed `full` migration is never rediscovered: its `index.js` is a re-export, which the
discovery pass does not recognise as a component. Re-runs are idempotent either way.

### Write failures

Each component's writes are guarded on their own. A failure reports that component as `error`,
names what is on disk, and the run continues so the report still covers everything else. The order
is `.vue` → `index.js` shim → twig deletion, and the `.vue` is removed again if the shim write
fails, so a failed component is left exactly as it was rather than half-migrated.

## Registration classes

Independent of the outcome, every component is classified by the `Component.*` call its directory is
registered through (`component-registry.ts`). Both reports carry the class: the CLI run as a column
per component plus a split summary line, the analysis markdown as a `By registration` summary table
and per-class count columns in the skip and TODO tables.

The class decides how far a component is migrated: only a plain `Component.register` takes the
destructive path, because only its template stands on its own.

| Class | Meaning | `--write` behaviour |
| --- | --- | --- |
| `register` | `Component.register('name', () => import('./dir'))` — the plain case | The full path, per the outcome above |
| `extend` | `Component.extend('child', 'parent', () => import('./dir'))` — child of another component | Skipped; it renders against bindings its parent declares |
| `override` | A directory registered through `Component.override('name', () => import('./dir'))` (none today, so the column is omitted) | Skipped; its template patches another component's markup |
| `unregistered` | No registration resolves to the directory (helpers, dynamically registered or dead components) | Draft only — `index.js` and the twig are kept, since this is also where an extend child lands when the registering file sits outside the scan root |

Inline `Component.override('name', { … })` configs own no directory and never reach the component
discovery. They are counted separately and reported as one info line (`72 inline
Component.override(...) configs found`) — reporting only, the codemod cannot convert them.

## Component names

The name a component is generated under comes from its registration, not from its directory — that
is what unlocks the CMS blocks/elements (`blocks/text/text/component` registers `sw-cms-block-text`)
and the `page/index` pages. Directories no registration resolves to keep their basename. The name
becomes the `.vue` filename, from which the build transform derives the runtime component name.

Two gates guard the derivation: a name the directory does not carry must be confirmed by the
template filename (`sw-cms-block-text.html.twig`), and a name registered for more than one directory
is never used. Either mismatch skips the component instead of guessing.

Registrations are collected from the whole Administration `src/` whenever the target lies inside it,
so running the codemod on a single module still resolves names registered in a parent directory.
For targets outside `src/` the scan root is the target itself.

## What is skipped on purpose

`mixins`, `Component.extend` children, `Component.override` registrations, `this.$super`/`this.$parent`,
`render()` components, root-level option spreads, components whose `name` option differs from their
component name, and components whose registered name neither the directory nor the template filename
confirms.

Two more are refused because base output cannot express them, and because the markup they would
produce compiles while rendering something different:

- a `{% block %}` wrapping a named slot — `<sw-block>` renders only its default slot, so the slot
  would be re-parented onto it and its content dropped;
- `{% parent %}` — meaningful only in an override, where the codemod does not write yet.

These need structural decisions a codemod should not guess. Everything else that is not understood
becomes a TODO comment in a draft instead of a silent conversion — including a `this.<member>` whose
name a local binding shadows, and module-level code outside the default export, which would run once
per component instance instead of once per module load.

## Structure

Each file answers exactly one question:

| File | Answers |
| --- | --- |
| `run-sfc-migration.ts` | How does a batch run work? CLI entry, discovery, twig-importer scan, file writes, report |
| `component-registry.ts` | Which dir belongs to which `Component.register`/`extend`/`override` call? One scan, feeds the component names and the classification |
| `convert-component.ts` | What happens to one component? The pipeline: template + script transform → prettier → validation gate |
| `transform-template.ts` | How does twig become a Vue template? (`{% block %}` → `<sw-block>`, comments, the `{% parent %}` and leftover-twig gates) |
| `template-ast.ts` | What does a converted template look like? Shared `@vue/compiler-dom` parse and the `<sw-block>` shape predicate |
| `assert-block-slots.ts` | Does a converted block swallow content? Named-slot children of `<sw-block>` |
| `normalize-cross-block-conditionals.ts` | How does a `v-if` chain survive a block boundary? Guard branches for `v-else`/`v-else-if` the conversion orphaned |
| `transform-script.ts` | In what order is the `<script setup>` assembled? Orchestrates parse → classify → rewrite → assemble |
| `option-handlers.ts` | How is each top-level option handled? One handler per option (`props`, `data`, `watch`, …) |
| `rewrite-this.ts` | Where does each `this.x` reference go? The rewrite pass, aware of both `this` binding and lexical scope |
| `tables.ts` | What converts to what? All conversion tables — the extension surface |
| `validate.ts` | Is the output safe to write? Real build transform + Vue compiler round-trip |
| `ast.ts` | Shared transform context and generic AST/text helpers — no conversion policy |
| `analyze-skips.ts` | Which features block the most components? Aggregates a dry run into a markdown report |
| `sfc-migration.spec.ts` + `__fixtures__/` | Snapshot of every fixture through the full pipeline + a tmpdir integration test of `--write` |
| `run-sfc-migration.spec.ts` | What does the runner do to files when a write fails? Rollback, twig-keep and existing-`.vue` behaviour |

## Extending the codemod

The conversion rules are data tables plus one handler per option:

- `tables.ts` — `INSTANCE_PROPS` (one entry per `this.$xyz` rewrite: replacement + required
  helper/import) and the `TODO_OPTIONS` / `SKIP_OPTIONS` tier assignment for top-level options.
- `option-handlers.ts` — `OPTION_HANDLERS`, one small handler per supported option (`props`,
  `data`, `computed`, `watch`, …). Promoting a feature means moving its key out of the TODO/SKIP
  set and adding a handler; the classification loop, the `this.` rewrite pass (`rewrite-this.ts`)
  and the assembly (`transform-script.ts`) stay untouched.
- New conversions are covered by dropping a fixture folder into `__fixtures__/` —
  `sfc-migration.spec.ts` snapshots every fixture automatically and runs the full validation gate.
