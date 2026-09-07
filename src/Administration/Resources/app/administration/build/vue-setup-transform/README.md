# vue-setup-transform

Lowers Shopware setup SFCs — plain Vue `<script setup>` files following the filename convention —
into the Composition-API extension contracts before Vue compiles them. Base components (`sw-thing.vue`)
keep their author body native and gain a generated `Shopware.Component.attachOverrides(...)` footer;
overrides (`sw-thing.override.vue`) lower to hidden components that register
`Shopware.Component.overrideComponentSetup()` callbacks.

Authoring rules and supported syntax are documented in
`technical-docs/03-extensibility/07-native-setup-authoring.md`. This README is the code map for
developers working on the transform itself.

## Directory map

| Path                      | What lives here                                                                                                 |
| ------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `index.ts`                | Entry point: parse → analyze script → analyze template → lower → replace the script content                     |
| `index.js` / `index.d.ts` | CommonJS bridge (jiti) plus its hand-written types for JS consumers                                             |
| `sfc-parser.ts`           | Finds the `<script setup>` block via `@vue/compiler-sfc` and normalizes its content boundaries                  |
| `script-analyzer.ts`      | Statement classification pass: produces the semantic model for lowering                                         |
| `script-analyzer/`        | Analyzer internals: macro registry, runtime bindings, setup inputs, validation, Babel utils                     |
| `template-analyzer/`      | Template pass: expression/template reference detection, and where a data scope or slot scope is needed          |
| `flow-analysis/`          | Identifier flow: which names an expression reads/writes, and every occurrence the base rename pass must rewrite |
| `lower/`                  | Code generation: mode dispatch (`index.ts`) plus the base and override lowerers and shared helpers              |
| `source-edits/`           | Chunk IR (`generated`/`original`), range transforms, rendering                                                  |
| `utils/`                  | Cross-cutting helpers: block normalization, script-block boundaries, Babel patterns, errors                     |
| `index.spec/`             | Transform test suite (per-macro, base, override, validation specs)                                              |

## Glossary

- **Runtime binding** — a top-level author declaration that becomes returned setup state:
  `const count = ref(0)`. Public if listed in `swDefinePublic({...})`, private otherwise.
- **Setup input declaration** — a declaration that reads a setup input through a macro/helper:
  `const props = defineProps<Props>()`. In base mode the macro call stays exactly where it was written
  and Vue compiles it; in override mode the `useSw*` helpers are emitted as generated headers above the
  author body. Either way the variable itself stays.
- **Exposable setup macro declaration** — a setup input declaration assigned to a plain identifier
  (`const emit = defineEmits(...)`). Exposed as private state so the template can read `emit`,
  `slots`, and `props.<name>`. The macro call itself is left in place for Vue.
- **Runtime input alias** — an override-only declaration reading a callback parameter:
  `const context = useSwContext()`, `useSwProps()`, `useSwPreviousState()`. Never returned as
  independent state, but forwarded to an override slot scope when the template references it. Base mode
  has no runtime input aliases - its body is native, so there is nothing to alias.
- **Marker macro** — `swDefinePublic({...})` / `swDefineOverride({...})`. Compile-time only; removed
  from the generated output after their entries are extracted.
- **Author alias** — the `__swSetupAuthor_<name>` name every top-level base runtime binding is renamed
  to, so the generated footer can re-declare the original name from `attachOverrides(...)`.
- **Public entries / override entries** — the shorthand binding names extracted from the markers.
- **Hoistable type declaration** — `interface`, `type`, or ambient `declare` statement; moved to the
  generated script root so hoisted macros can still resolve the names.
- **Source edit** — a range of the SFC plus the chunks that replace it. **Lowering produces every one of
  them**, including the ones outside the script block (an override's generated `<template>`, a base
  `<sw-block>`'s `:data="$dataScope"`, an override block's `#default` slot scope). Both analyses report
  positions and names; no generated syntax is decided outside `lower/`.
- **Marker statements / rename targets** — locations the analyzer reports, never edits. Override lowering
  strips imports, type declarations and markers from the body it moves into the callback; base lowering
  strips only the markers and rewrites every rename target to its author alias, because its body never
  moves. Which ranges are removed, and what a rename is replaced with, belongs to the lowerer.
- **Override-private namespace** — the module-root `Symbol()` (bound to `__swSetupNamespace`) used as a
  **computed** key under the reserved `__swOverride` slot-scope channel, through which an override's
  non-public bindings reach its `<sw-block extends>` template content. Emitted only when the override
  actually forwards locals. Uniqueness comes from the symbol, so the binding name can be fixed.
- **Chunks** — the source IR: `generated` (compiler-owned text) and `original` (a slice of the author's
  SFC, kept addressable for sourcemaps). There is no re-indent or trim wrapper: the transform does not
  beautify its output, so copied lines keep their original columns.
