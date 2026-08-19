# Custom Plugin Editor Template

These files are templates for local plugin development in `custom/`.

Copy:

- `eslint.config.mjs` to `custom/eslint.config.mjs`
- `plugin-tsconfig.json` to `custom/plugins/<PluginName>/tsconfig.json`

The template intentionally stays close to the Administration resolver shape.
The plugin `tsconfig.json` is standalone and uses paths relative to
`custom/plugins/<PluginName>`. It uses `moduleResolution: "node"` and `baseUrl`
so local plugin imports behave like Administration imports.
`ignoreDeprecations: "5.0"` suppresses the current TypeScript deprecation
warnings for those options while the Administration tooling still depends on
them.

## Known fragility: the deep `node_modules` imports

`eslint.config.mjs` imports `eslint-plugin-vue` and `@typescript-eslint/parser`
through explicit paths into the Administration's `node_modules`
(`.../node_modules/eslint-plugin-vue/lib/index.js`,
`.../node_modules/@typescript-eslint/parser/dist/index.js`), and it reaches into
`eslint-plugin-vue`'s internal `flat/recommended` entry named
`vue/base/setup-for-vue` to borrow its parser.

Both are private layout rather than published entry points. There is no
`node_modules` at `custom/`, so package resolution cannot be used from there —
these paths are the workaround, not the intended mechanism. Two consequences to
know about:

- A major bump of either package can move or rename those files, or rename that
  internal config, and every copied workspace breaks at once. Nothing in CI
  catches it: this directory is excluded from the Administration's ESLint run and
  has no test.
- Update the paths after such a bump. A normalized-path resolution is planned to
  replace this.
