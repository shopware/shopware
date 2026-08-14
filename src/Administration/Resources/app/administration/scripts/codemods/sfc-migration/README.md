# SFC Migration Codemod

Converts Options API Administration components (`index.js` + `*.html.twig`) into native setup SFCs
(`<component-name>.vue` with `swDefinePublic`, see
`technical-docs/03-extensibility/07-native-setup-authoring.md`).

## Usage

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
| `full` | Everything converted, output validated | Writes `<dir>/<dir-name>.vue`, shrinks `index.js` to a re-export shim, deletes the twig (kept if another file still imports it) |
| `partial` | Converted with `// TODO(sfc-migration)` comments | Writes the `.vue` draft only; `index.js` + twig stay untouched, the component keeps running as before |
| `skipped` | Structural blocker | Writes nothing; the report names the reason |
| `already-migrated` | A `.vue` with the component's name exists | Writes nothing (re-runs are idempotent) |

Every generated SFC must pass the real build transform (`build/vue-setup-transform`) plus Vue's own
`compileScript`/`compileTemplate` before it is written — a non-compiling file is never produced.

## Registration classes

Independent of the outcome, every component is classified by the `Component.*` call its directory is
registered through (`component-registry.ts`). Both reports carry the class: the CLI run as a column
per component plus a split summary line, the analysis markdown as a `By registration` summary table
and per-class count columns in the skip and TODO tables.

| Class | Meaning |
| --- | --- |
| `register` | `Component.register('name', () => import('./dir'))` — the plain case |
| `extend` | `Component.extend('child', 'parent', () => import('./dir'))` — child of another component, usually without an own template |
| `override` | A directory registered through `Component.override('name', () => import('./dir'))` (none today, so the column is omitted) |
| `unregistered` | No registration resolves to the directory (helpers, dynamically registered or dead components) |

Inline `Component.override('name', { … })` configs own no directory and never reach the component
discovery. They are counted separately and reported as one info line (`72 inline
Component.override(...) configs found`) — reporting only, the codemod cannot convert them.

## What is skipped on purpose

`mixins`, `Component.extend` children, `this.$super`/`this.$parent`, `render()` components,
root-level option spreads, and components whose `name` option differs from their directory name.
These need structural decisions a codemod should not guess. Everything else that is not understood
becomes a TODO comment in a draft instead of a silent conversion.

## Structure

Each file answers exactly one question:

| File | Answers |
| --- | --- |
| `run-sfc-migration.ts` | How does a batch run work? CLI entry, discovery, twig-importer scan, file writes, report |
| `component-registry.ts` | Which dir belongs to which `Component.register`/`extend`/`override` call? One scan, feeds the classification |
| `convert-component.ts` | What happens to one component? The pipeline: template + script transform → prettier → validation gate |
| `transform-template.ts` | How does twig become a Vue template? (`{% block %}` → `<sw-block>`, comments, `{% parent %}`, leftover-twig check) |
| `normalize-cross-block-conditionals.ts` | How does a `v-if` chain survive a block boundary? Guard branches for `v-else`/`v-else-if` the conversion orphaned |
| `transform-script.ts` | In what order is the `<script setup>` assembled? Orchestrates parse → classify → rewrite → assemble |
| `option-handlers.ts` | How is each top-level option handled? One handler per option (`props`, `data`, `watch`, …) |
| `rewrite-this.ts` | Where does each `this.x` reference go? The scope-aware rewrite pass |
| `tables.ts` | What converts to what? All conversion tables — the extension surface |
| `validate.ts` | Is the output safe to write? Real build transform + Vue compiler round-trip |
| `ast.ts` | Shared transform context and generic AST/text helpers — no conversion policy |
| `analyze-skips.ts` | Which features block the most components? Aggregates a dry run into a markdown report |
| `sfc-migration.spec.ts` + `__fixtures__/` | Snapshot of every fixture through the full pipeline + a tmpdir integration test of `--write` |

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
