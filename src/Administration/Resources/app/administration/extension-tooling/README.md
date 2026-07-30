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

## Why `moduleResolution: "node"`

`tsconfig.base.json` declares **no** `paths`, and resolves modules the classic
Node way. That combination is deliberate:

- Classic Node resolution ignores the `exports` field, so exports-gated subpaths
  such as `@shopware-ag/meteor-admin-sdk/es/*` are found through the filesystem.
  Measured against a real extension, `"Bundler"` leaves 82 unresolved modules
  (TS2307) and `"node"` leaves 0.
- With no host `paths`, tsconfig inheritance — which replaces `paths` wholesale —
  cannot destroy host resolution. `paths` belongs entirely to the extension.
- It is the resolver the Administration compiles its own program with.

The honest downside: Vite honours `exports`, the type check does not, so TypeScript
can in theory resolve a subpath the bundler would refuse. That is already the
status quo for the Administration itself.

## Boundaries

- A green type check is not a green build. `paths` and the type program satisfy
  TypeScript and ESLint; an extension's Vite build resolves its runtime aliases
  on its own.
- The checker needs an installation. An extension repository therefore cannot
  lint without Shopware — that is the point: you check against the _installed_
  version. In CI, install Shopware, mount the extension and run
  `bin/console administration:extension:check <Name>`.
