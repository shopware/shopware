# Administration extension tooling

Shared TypeScript and ESLint presets for Administration extensions, plus the type
surface an extension sees.

| File                 | Purpose                                                                                             |
| -------------------- | --------------------------------------------------------------------------------------------------- |
| `tsconfig.base.json` | Compiler options for an extension program.                                                          |
| `admin-types.d.ts`   | The type surface of the _installed_ Administration, injected via `files`.                           |
| `eslint.mjs`         | Flat-config factory. Every parser and plugin resolves from the Administration's own `node_modules`. |
| `legacy-twig.mjs`    | Twig template linting for extensions still using the legacy component factory.                      |

Nothing here is meant to be edited by an extension. The runner
(`bin/console administration:extension:check`, Composer twin
`composer admin:extension:check`) generates one ephemeral program per extension
source root below `var/admin-extension-tooling/` and points the Administration's
own `tsc` and `eslint` at it, so an extension needs to commit nothing at all to
be type-checked and linted.

## Editor support

```bash
bin/console administration:extension:setup
```

Links the installed Administration into `<projectRoot>/node_modules` — every
top-level entry of its `node_modules`, plus `src` as the package its own sources
import each other as, plus an ambient `@types` entry carrying `admin-types.d.ts`.

Bare specifiers resolve by walking `node_modules` upwards, and the project root is
an ancestor of every extension in both layouts, so this gives an extension with
**no config at all** the Administration types, completion, and the ESLint binary
plus every parser and plugin from the same tree — no `paths` mapping, no IDE
settings file, no version skew.

Notes:

- The directory is replaced on every run, never merged, so orphaned links are
  impossible. Re-run it after an `npm ci` in the Administration.
- It ignores itself through a `.gitignore` holding `*`, so `git status` stays
  clean, and that file is also how the command recognises its own farm: a
  `node_modules` it did not create is never replaced.
- Link targets are relative, so a farm built inside a container still resolves
  from an editor on the host, where the same tree is mounted elsewhere.
- On Windows, directory links are junctions (no Developer Mode needed); links to
  files may still fail and are reported. `administration:extension:check` does not
  depend on the farm.
- The ambient types make an editor pull the whole Administration into the program
  for a config-less file. Diagnostics inside those host files are the price of
  needing no configuration; they are reported against host files, not yours.

## Why `moduleResolution: "node"`

`tsconfig.base.json` declares **no** `paths`, and resolves modules the classic
Node way. That combination is deliberate:

- Classic Node resolution ignores the `exports` field, so exports-gated subpaths
  such as `@shopware-ag/meteor-admin-sdk/es/*` are found through the filesystem.
  Measured against a real extension, `"Bundler"` leaves 82 unresolved modules
  (TS2307) and `"node"` leaves 0.
- With no host `paths` in the preset, tsconfig inheritance — which replaces `paths`
  wholesale — cannot destroy host resolution from a config that extends it.
- It is the resolver the Administration compiles its own program with.

The honest downside: Vite honours `exports`, the type check does not, so TypeScript
can in theory resolve a subpath the bundler would refuse. That is already the
status quo for the Administration itself.

## Where the host mappings live

The preset stays free of `paths`, but a program still needs them, and the runner
writes them into the program it owns — where no extension config can replace them.

`admin-types.d.ts` pulls the Administration's own sources into every extension
program, and those sources import each other as `src/…`, which the Administration's
own `baseUrl` resolves. A program without an equivalent mapping cannot resolve
`ShopwareClass`, so the global `Shopware` is declared as the TypeScript _error
type_ — and an error type accepts every property access without complaint. The
type check then passes while checking nothing at all.

The runner therefore derives the mappings from the Administration's own
`tsconfig.json` and adds a wildcard standing in for its `baseUrl` and its
`node_modules`, so an extension importing a host package resolves against the
installed version too. An extension's own `paths` win over both.

If a run reports unresolved modules in the host sources, it fails with exit code 3
rather than reporting a clean type check.

## Boundaries

- A green type check is not a green build. `paths` and the type program satisfy
  TypeScript and ESLint; an extension's Vite build resolves its runtime aliases
  on its own.
- The checker needs an installation. An extension repository therefore cannot
  lint without Shopware — that is the point: you check against the _installed_
  version. In CI, install Shopware, mount the extension and run
  `bin/console administration:extension:check <Name>`.
