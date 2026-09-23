# vue-setup-transform

Lowers native setup SFCs into plain Vue SFCs before Vue compiles them. The filename decides the mode:
`sw-thing.vue` is a base component, `sw-thing.override.vue` an override of it. Authoring rules are in
`technical-docs/03-extensibility/07-native-setup-authoring.md`; this file maps the code.

Generated code only talks to the versioned runtime `Shopware.Component.__setupRuntime.v1`
(`attach`, `expose`, `late`, `override`).

- **Base**: the body stays a native `<script setup>`. Every top-level runtime binding is renamed to
  `__swSetupAuthor_<name>`, and a footer re-declares the original names from `attach()`, which applies the
  overrides. References inside functions run after setup, so they read `__swSetupLate.<name>` instead,
  which resolves to the override-aware binding. `swDefinePublic()` also generates `defineExpose()`. Each
  `<sw-block name>` gets `:data="$dataScope"`.
- **Override**: the `<script setup>` becomes a plain `<script>` that registers the body as an
  `override()` callback at module scope. Every override local reaches `<sw-block extends>` content through
  a generated `#default` slot scope: public bindings by name, the rest under a module-scope `Symbol()`
  inside `__swOverride`.

## Code map

| Path                         | Role                                                                               |
| ---------------------------- | ---------------------------------------------------------------------------------- |
| `index.ts`                   | Entry: `transformShopwareSetupSfc`, `analyzeShopwareSetupSfc` (no code, no map)    |
| `index.js` / `index.d.ts`    | CommonJS bridge (jiti) and its types                                               |
| `naming.ts`                  | Filename conventions and reserved names, shared with the tooling                   |
| `sfc-parser.ts`              | Finds the `<script setup>` block and Vue's template AST                            |
| `script-analyzer.ts`         | Classifies top-level statements, validates, collects rename targets (Babel scopes) |
| `script-analyzer/macros.ts`  | The macro table and the `swDefine*` marker rules                                   |
| `template-analyzer/`         | `<sw-block>` rules, insertion points, the override write guard                     |
| `lower/`                     | Code generation as MagicString edits                                               |
| `sourcemap.ts`               | Keeps generated code unmapped in the sourcemap                                     |
| `shopware-setup-macros.d.ts` | Global types of the macros and `useSw*` helpers                                    |
| `utils/transform-error.ts`   | `ShopwareSetupTransformError` and source-range helpers                             |
