# Resolve-and-Diagnose Response

The response body of the diagnose endpoint ([diagnose.md](diagnose.md)): the resolutions map, the diagnostics report, and the violation codes.

`200 OK` with `{ resolutions, diagnostics }` — never persisted, never cached.

```json
{
  "resolutions": {
    "<elementId>": [
      {
        "key": "media",
        "kind": "reference",
        "required": false,
        "type": null,
        "default": null,
        "fqcn": "Shopware\\Core\\Content\\Media\\MediaEntity",
        "resolved": {
          "origin": "loader",
          "contextKey": null,
          "providerElementId": null,
          "path": null,
          "distribution": null,
          "contextType": null,
          "loaderSource": "entity",
          "configTemplate": { "entity": "media", "property": "mediaId" },
          "configComplete": true
        },
        "candidates": [
          {
            "origin": "loader",
            "contextKey": null,
            "providerElementId": null,
            "path": null,
            "distribution": null,
            "contextType": null,
            "loaderSource": "entity",
            "configTemplate": { "entity": "media", "property": "mediaId" },
            "configComplete": true
          }
        ]
      }
    ]
  },
  "diagnostics": {
    "wellFormed": true,
    "resolvable": true,
    "violations": []
  }
}
```

`resolutions` is keyed by element id; each entry is the list of that element's declared properties with how each is (or is not) filled, and encodes as `{}` when empty (never `[]`). `kind` is `primitive` or `reference`; a `reference` property carries a `resolved` candidate (or `null`) and the full `candidates` list. A candidate's `origin` is `parent` (an ancestor provider, `providerElementId` naming the providing element), `root` (a root-ambient offer from the layout's bound root source, `providerElementId` always `null` because no element supplies it), `loader` (a data loader), or `stored` (the element's own applied wiring — a stored reference wiring whose produced type resolves and is assignable to the declared FQCN; it only ever fills `resolved` directly, never a `candidates` menu entry). A `stored` candidate is not loader-shaped: its `loaderSource`, `configTemplate`, and `configComplete` all serialize as `null` (clients branch on `origin` before reading them).

A client derives the specifications applicable to an element from the `bindingSpecifications` map on that element's type entry in [`content-system-element-types.json`](../../Layout/Type/docs/introspection.md) (`bindingSpecifications[element.component]`) — the ids from the [Binding specifications](../../Binding/docs/introspection.md) fold that a client may pass as `bindingSpecificationId` to a bind-element action.

`diagnostics.wellFormed` is true when there are no intrinsic-scope error violations (the persistence gate predicate); `diagnostics.resolvable` is true when there are no binding-scope error violations (the serving gate predicate, meaningful only when a source was bound). Each violation derives its `scope` and `severity` from its `code`:

| `code`                   | `scope`   | `severity` |
|--------------------------|-----------|------------|
| `unregistered_component`     | intrinsic | error      |
| `duplicate_element_id`       | intrinsic | error      |
| `invalid_config`             | intrinsic | error      |
| `mismatched_reference_type`  | intrinsic | error      |
| `mismatched_property_type`   | intrinsic | error      |
| `unknown_style_option`       | intrinsic | error      |
| `orphaned_provider`          | intrinsic | warning    |
| `unresolved_required`        | binding   | error      |
| `ambiguous_required`         | binding   | error      |
| `broken_required_chain`      | binding   | error      |
| `unfilled_required_input`    | binding   | error      |
| `unresolved_optional`        | binding   | warning    |

`mismatched_reference_type` flags a stored reference wiring (any `dataRequirements` entry the element carries, not only one recorded in `attributedSpecifications`) whose resolved produced type is not assignable to the property's declared FQCN. It is intrinsic, not binding-scope: the mismatch is a property of the element's own stored wiring, independent of any bound `rootSource`. A config that fails to resolve (a client defect) is `invalid_config` instead; a config that resolves and fits produces no violation — it becomes a `stored` resolution instead (see the `origin` note above).

`mismatched_property_type` flags a stored `properties` value that disagrees with the primitive type the element's type declares for that key, one violation per key, with `key` carrying the property name. `string` takes a string, `integer` an integer, `number` an integer or a float, `boolean` a boolean, and a union whose members are all primitive takes a value matching any one of them; `null` is admissible under all of them, because whether a key may be absent or null is `unresolved_required`'s business. A key declared as `object`, as an FQCN, or as a union carrying either constrains nothing and is never flagged, and neither is a `resolvedBy` storage key the type does not also declare as a property. Like `unknown_style_option`, it never appears on a DAL write: the write-path constraint pass refuses such a tree before the gate that produces these diagnostics runs.
