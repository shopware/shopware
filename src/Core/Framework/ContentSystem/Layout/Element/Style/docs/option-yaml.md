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
  label: "Column Span"
  description: "How many grid columns the element spans."
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
- **`default`** (optional): pre-fills the editor; the write boundary fills the missing breakpoints of a partial map from it, see [option-model.md](option-model.md).
- **`adminUI`** (optional): an opaque block passed through verbatim to the Administration. See [Presentation hints belong to the editor, and the server never branches on them](../../../../docs/principles/type-declarations.md#presentation-hints-belong-to-the-editor-and-the-server-never-branches-on-them).
- **`kind`** (optional): declares that the option's value gets a kind-specific canonicalisation at the write boundary. Its only defined value is `box-spacing`, which canonicalises the value into explicit four-part CSS (`top right bottom left`). Any other value fails the declaration's `Assert\Choice`: `YamlStyleOptionLoader` fails hard and `DatabaseStyleOptionLoader` skips the row with a warning. No shipped core option declares `kind`. Omitted, the value is stored as authored.

There is no `pattern`: strings are bounded by `maxLength`, see [A style option value carries no regex pattern](../../../../docs/principles/type-declarations.md#a-style-option-value-carries-no-regex-pattern).

## Breakpoints

Values are set per breakpoint. The breakpoint key set is the fixed framework primitive `xs, sm, md, lg, xl, xxl`; it is not extensible. Each breakpoint is optional, so a responsive option may set only some of them. Breakpoints are **mobile first**, mirroring the Storefront's Bootstrap breakpoints. A value applies from its breakpoint upward until a larger breakpoint overrides it, so a value set only at `xs` affects every width. The backend stores and serves the map verbatim; rendering consumers apply this cascade. On an element, the stored shape is `option => breakpoint => value`:

```json
{ "col-span": { "md": 6, "lg": 4 }, "display": { "xs": false } }
```

Here `col-span` is 6 from `md` upward and 4 from `lg` upward, and `display: false` at `xs` hides the element at every width.
