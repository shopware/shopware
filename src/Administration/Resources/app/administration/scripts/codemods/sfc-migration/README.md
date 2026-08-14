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

## What is skipped on purpose

`mixins`, `Component.extend` children, `this.$super`/`this.$parent`, `render()` components,
root-level option spreads, and components whose `name` option differs from their directory name.
These need structural decisions a codemod should not guess. Everything else that is not understood
becomes a TODO comment in a draft instead of a silent conversion.

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
