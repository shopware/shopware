# Style Option Introspection

The two read surfaces a declaration feeds, and the Admin API endpoint that serves the registered options.

`StyleOptionSpecification::toSchema()` feeds two surfaces, both from the one registry, so introspection and validation never drift:

- a dedicated endpoint `GET /api/_info/content-system-style-options.json` (`InfoController::getContentSystemStyleOptions()`), serving the options keyed by their wire name;
- a folded `styleOptions` key on `GET /api/_info/content-system-element-types.json`, since every option is settable on every type.

`toSchema()` (the client contract) always emits `breakpointAware` resolved to a concrete bool, so a client never has to assume the default. `normalize()` (internal DB storage of an app option) follows the `!== null` convention shared with `enum` / `range` / `maxLength` / `default` instead: it omits an absent flag but emits an explicit `false`. Both resolve to the same effective value (absent ⇒ `true`).

## Style options

`GET /api/_info/content-system-style-options.json`

The registered universal style options — presentation attributes (alignment, span, spacing, display) settable on every element regardless of its type — keyed by their wire name. Backed by the style option registry (`Layout/Element/Style/Registry`), serialized via `StyleOptionSpecification::toSchema()`. It is a `GET`, returns JSON, requires Admin API auth, and is served by `Framework/Api/Controller/InfoController`. The same options are folded into the `styleOptions` key on each entry of [`content-system-element-types.json`](../../../Type/docs/introspection.md).

Response:

```json
{
  "styleOptions": {
    "col-span": {
      "type": "integer",
      "enum": null,
      "range": { "min": 1, "max": 12 },
      "maxLength": null,
      "default": null,
      "adminUI": { "component": "number", "label": "Column Span" },
      "breakpointAware": true
    }
  }
}
```

`range` bounds `integer`/`number` options, `maxLength` bounds `string` options (a string with no declared `maxLength` defaults to 255); `default` is advisory only — an introspection/Admin pre-fill hint, never seeded into stored element JSON or applied at serve time. `breakpointAware` marks whether the option's value is set per breakpoint (`xs`, `sm`, `md`, `lg`, `xl`, `xxl`) or as a single flat scalar.

Full field-level schema: [content-system-style-options.json](../../../../../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-style-options.json).
