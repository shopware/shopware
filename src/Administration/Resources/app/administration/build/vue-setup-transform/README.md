# vue-setup-transform

Lowers Shopware setup SFCs — plain Vue `<script setup>` files following the filename convention —
into the Composition-API extension contracts before Vue compiles them. Base components (`sw-thing.vue`)
lower to `Shopware.Component.createExtendableSetup(...)`; overrides (`sw-thing.override.vue`) lower to
hidden components that register `Shopware.Component.overrideComponentSetup()` callbacks.

Authoring rules and supported syntax are documented in
`technical-docs/03-extensibility/07-native-setup-authoring.md`. This README is the code map for
developers working on the transform itself.

## Directory map

| Path | What lives here |
| --- | --- |
| `index.ts` | Entry point: parse → analyze script → analyze template → lower → apply edits |
| `index.js` / `index.d.ts` | CommonJS bridge (jiti) plus its hand-written types for JS consumers |
| `sfc-parser.ts` | Finds the `<script setup>` block via `@vue/compiler-sfc` and normalizes it |
| `script-analyzer.ts` | Statement classification pass: produces the semantic model for lowering |
| `script-analyzer/` | Analyzer internals: macros, runtime bindings, setup inputs, validation, hoisted-macro-argument guard, Babel utils |
| `template-analyzer/` | Template pass: expression/template reference detection, slot-scope merging, data-scope injection |
| `lower.ts`, `lower/` | Code generation: base and override lowerers plus shared chunk helpers |
| `source-edits/` | Chunk IR (`generated`/`original`/`trim`/`indent`), range transforms, rendering |
| `utils/` | Cross-cutting helpers: block normalization, script-tag handling, Babel patterns, errors |
| `index.spec/` | Transform test suite (per-macro, base, override, validation specs) |

## Glossary

- **Runtime binding** — a top-level author declaration that becomes returned setup state:
  `const count = ref(0)`. Public if listed in `swDefinePublic({...})`, private otherwise.
- **Setup input declaration** — a declaration that reads a setup input through a macro/helper:
  `const props = defineProps<Props>()`. The macro call is hoisted or replaced; the variable stays.
- **Exposable setup macro declaration** — a setup input declaration assigned to a plain identifier
  (`const emit = defineEmits(...)`). Exposed as private state so the template can read `emit`,
  `slots`, and `props.<name>`.
- **Runtime input alias** — `const context = useSwContext()` (and in overrides `useSwProps()`,
  `useSwPreviousState()`). Never returned as independent state, but forwarded to an override slot
  scope when the template references it.
- **Setup input replacement** — the source range where a macro call site is swapped for a generated
  callback input, e.g. `defineEmits(...)` → `(__swSetupContext.emit)`.
- **Marker macro** — `swDefinePublic({...})` / `swDefineOverride({...})`. Compile-time only; removed
  from the generated output after their entries are extracted.
- **Public entries / override entries** — the shorthand binding names extracted from the markers.
- **Hoistable type declaration** — `interface`, `type`, or ambient `declare` statement; moved to the
  generated script root so hoisted macros can still resolve the names.
- **Body removals** — analyzer ranges (imports, type declarations, markers, kept-at-root macros)
  stripped from the author code before it moves into the setup callback.
- **Override-private namespace** — the deterministic per-file key (`<file>_<5-hex-sha1>`) under the
  reserved `__swOverride` slot-scope channel through which an override's non-public bindings reach
  its `<sw-block extends>` template content.
- **Chunks** — the source IR: `generated` (compiler-owned text), `original` (a slice of the author's
  SFC, kept addressable for sourcemaps), and the deferred `trim`/`indent` wrappers around them.
