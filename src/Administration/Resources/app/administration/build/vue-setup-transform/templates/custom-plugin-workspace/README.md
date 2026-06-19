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
