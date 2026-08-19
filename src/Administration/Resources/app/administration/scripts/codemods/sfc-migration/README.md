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

| Outcome | Meaning | `--write` behavior |
| --- | --- | --- |
| `full` | Everything converted, output validated | Writes `<dir>/<component-name>.vue`; with `--replace-originals`, an unambiguous plain registration may also replace `index.js` with a re-export shim. Twig is retained |
| `partial` | Converted with `// TODO(sfc-migration)` comments | Writes the `.vue` draft only; `index.js` + twig stay untouched, the component keeps running as before |
| `skipped` | Structural blocker | Writes nothing; the report names the reason |
| `already-migrated` | A `.vue` with the component's name exists | Writes nothing; the reason says whether it is an earlier draft, a half-migration, or a file this codemod never wrote |
| `error` | The conversion or a write threw | Reports what ended up on disk; the run continues and exits `1` |

Every generated SFC must pass the real build transform (`build/vue-setup-transform`) plus Vue's own
`compileScript`/`compileTemplate` before it is written — a non-compiling file is never produced.
That gate proves the output *compiles*, not that it *behaves the same*, so shapes that would compile
into different behaviour are refused separately (see below).

A component whose entry point was explicitly replaced is never rediscovered: its `index.js` is a
re-export, which the discovery pass does not recognise as a component. Draft-only re-runs report
the existing draft and leave it untouched.

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

| Class | Meaning | `--write` behaviour |
| --- | --- | --- |
| `register` | `Component.register('name', () => import('./dir'))` — the plain case | Writes a draft with `--write`; only the unambiguous full path may replace `index.js` with `--replace-originals` |
| `extend` | `Component.extend('child', 'parent', () => import('./dir'))` — child of another component | Skipped; it renders against bindings its parent declares |
| `override` | A directory registered through `Component.override('name', () => import('./dir'))` (none today, so the column is omitted) | Skipped; its template patches another component's markup |
| `unregistered` | No registration resolves to the directory (helpers, dynamically registered or dead components) | Draft only — `index.js` and Twig are kept |

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
name a local binding shadows. Module-level code is retained in a normal script block so it still runs
once per module load.

## Structure

Each file answers exactly one question:

| File | Answers |
| --- | --- |
| `run-sfc-migration.ts` | How does a batch run work? CLI entry, clean-tree guard, discovery, file writes, report |
| `component-source-model.ts` | Which source files, registrations, and exact Twig binding belong together? The one structural read of the tree |
| `convert-component.ts` | What happens to one component? The pipeline: template + script transform → prettier → validation gate |
| `transform-template.ts` | How does twig become a Vue template? (`{% block %}` → `<sw-block>`, comments, the `{% parent %}` and leftover-twig gates) |
| `template-ast.ts` | What does a converted template look like? Shared `@vue/compiler-dom` parse and the `<sw-block>` shape predicate |
| `assert-block-slots.ts` | Does a converted block swallow content? Named-slot children of `<sw-block>` |
| `normalize-cross-block-conditionals.ts` | How does a `v-if` chain survive a block boundary? Guard branches for `v-else`/`v-else-if` the conversion orphaned |
| `transform-script.ts` | In what order is the `<script setup>` assembled? Orchestrates parse → collect → rewrite → render |
| `option-handlers.ts` | How is each top-level option handled? One handler per option (`props`, `data`, `watch`, …) |
| `rewrite-this.ts` | Where does each `this.x` reference go? The rewrite pass, aware of both `this` binding and lexical scope |
| `tables.ts` | What converts to what? All conversion tables — the extension surface |
| `validate.ts` | Is the output safe to write? Real build transform + Vue compiler round-trip |
| `ast.ts` | Shared transform context and generic AST/text helpers — no conversion policy |
| `sfc-migration.spec.ts` + `__fixtures__/` | What does one component convert into? A snapshot of every fixture through the full pipeline |
| `run-sfc-migration.spec.ts` | What does the runner do to files? CLI exit codes, draft/replacement modes, name derivation, and existing-`.vue` behaviour |
| `spec-helpers.ts` | Temp-tree helpers shared by the specs that build a throwaway component tree |
| `runtime-equivalence-*.ts` | Does a supported shape execute equivalently, or stay conservative? |

## Extending the codemod

The conversion rules are data tables plus one handler per option:

- `tables.ts` — `INSTANCE_PROPS` (one entry per `this.$xyz` rewrite: replacement + required
  helper/import) and the `OPTION_TIERS` (`skip` / `todo`) assignment for top-level options.
- `option-handlers.ts` — `OPTION_HANDLERS`, one small handler per supported option (`props`,
  `data`, `computed`, `watch`, …). Promoting a feature means dropping its key from `OPTION_TIERS`
  and adding a handler — never both, because the tier is read first; the classification loop, the
  `this.` rewrite pass (`rewrite-this.ts`) and the render pass (`transform-script.ts`) stay untouched.
- New conversions are covered by dropping a fixture folder into `__fixtures__/` —
  `sfc-migration.spec.ts` snapshots every fixture automatically and runs the full validation gate.
