# Style Option YAML

The file format of one option declaration, and the breakpoint key set its values are keyed by.

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
- **`maxLength`** (optional, declarable on `string` only): caps the stored string. A `string` or `number` with no `maxLength` declared is still capped at 255, so a client cannot store an unbounded value (including a long numeric string); `integer` and `boolean` are unaffected.
- **`default`** (optional): advisory only — an introspection and Admin pre-fill hint. It is **not** seeded into stored elements and **not** applied at serve time, so an element's `style` stays omitted when empty.
- **`adminUI`** (optional): an opaque block passed through verbatim to the Administration; the backend never interprets it.
- **`kind`** (optional): declares that the option's value gets a kind-specific canonicalisation at the write boundary. Its only defined value is `box-spacing`, which canonicalises the value into explicit four-part CSS (`top right bottom left`). Any other value is rejected at load. Omitted, the value is stored as authored.

There is deliberately no `pattern` / regex: an app-supplied regex compiled from untrusted data and run on every write is a ReDoS vector, so strings are bounded by `maxLength` instead.

## Breakpoints

Values are set per breakpoint. The breakpoint key set is the fixed framework primitive `xs, sm, md, lg, xl, xxl`; it is not extensible. Each breakpoint is optional, so a responsive option may set only some of them. Breakpoints are **mobile first**, mirroring the Storefront's Bootstrap breakpoints: a value applies from its breakpoint upward until a larger breakpoint overrides it, so a value set only at `xs` affects every width. The backend stores and serves the map verbatim; rendering consumers apply this cascade. On an element, the stored shape is `option => breakpoint => value`:

```json
{ "col-span": { "md": 6, "lg": 4 }, "display": { "xs": false } }
```

Here `col-span` is 6 from `md` upward and 4 from `lg` upward, and `display: false` at `xs` hides the element at every width.
