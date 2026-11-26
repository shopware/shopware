# Style Option YAML

## YAML Structure

The declaration is **flat** — there is no `meta:` wrapper (this differs from element-type YAML):

```yaml
type: integer
range:
  min: 1
  max: 12
adminUI:
  component: "number"
  label: "Custom Size"
  description: "An example custom numeric style option."
```

A string-enum option instead:

```yaml
type: string
default: "auto"
enum:
  - "auto"
  - "start"
  - "center"
  - "end"
adminUI:
  component: "select"
  label: "Align Self"
```

- **`type`** (required): one of `string`, `integer`, `number`, `boolean`.
- **`enum`** (optional, primitives): the allowed value set.
- **`range`** (optional, `integer` / `number`): `min` and/or `max` bounds.
- **`maxLength`** (optional, declarable on `string` only): caps the stored string. A `string` or `number` with no `maxLength` declared is still capped at 255, so a client cannot store an unbounded value (including a long numeric string). `integer` and `boolean` are unaffected.
- **`default`** (optional): pre-fills the editor. See [option-model.md](option-model.md).
- **`adminUI`** (optional): an opaque block passed through verbatim to the Administration. An option shown as a `radio-panel` declares its values in `enum`, unless it is a `string` option with `breakpointAware: false`; without one, a click on one of its options throws an error in the Administration and emits no value. See [Presentation hints belong to the editor, and the server never branches on them](../../../../docs/principles/type-declarations.md#presentation-hints-belong-to-the-editor-and-the-server-never-branches-on-them).
- **`kind`** (optional): declares that the option's value is normalized at the [write boundary](../../../../docs/principles/README.md#terms-that-are-easy-to-confuse). Its only defined value is `box-spacing`, which normalizes the value into explicit four-part CSS (`top right bottom left`). Any other value fails the declaration's `Assert\Choice`: both `YamlStyleOptionLoader` and `DatabaseStyleOptionLoader` fail the load with `STYLE_OPTION_LOAD_FAILED`. No shipped core option declares `kind`. Omitted, the value is stored as authored.

There is no `pattern`: strings are bounded by `maxLength`, see [A style option value carries no regex pattern](../../../../docs/principles/type-declarations.md#a-style-option-value-carries-no-regex-pattern).

## Breakpoints

Values are set per breakpoint. The framework accepts the fixed key set `xs, sm, md, lg, xl, xxl`.
LG provides the base value.
XS inherits SM if XS is absent and SM is present.
XXL inherits XL if XXL is absent and XL is present.
All remaining missing values inherit LG.
The write boundary preserves the supplied entries and seeds a missing LG entry from the declared `default`.
The storefront resolves inherited values when rendering.

```json
{ "display": { "lg": true, "sm": false }, "align-self": { "lg": "center", "xl": "start" } }
```

The element is hidden at XS and SM and visible at MD and above.
Its alignment is `start` at XL and XXL and `center` at every other breakpoint.
