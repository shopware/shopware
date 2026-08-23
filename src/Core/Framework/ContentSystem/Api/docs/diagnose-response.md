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

`resolutions` is keyed by element id; each entry is the list of that element's declared properties with how each is (or is not) filled, and encodes as `{}` when empty (never `[]`). `kind` is `primitive` or `reference`; a `reference` property carries a `resolved` candidate (or `null`) and the full `candidates` list. A candidate's `origin` is `parent` (an ancestor/root provider), `loader` (a data loader), or `stored` (the element's own applied wiring — a stored reference wiring whose produced type resolves and is assignable to the declared FQCN; it only ever fills `resolved` directly, never a `candidates` menu entry). A `stored` candidate is not loader-shaped: its `loaderSource`, `configTemplate`, and `configComplete` all serialize as `null` (clients branch on `origin` before reading them).

A client derives the specifications applicable to an element from the `bindingSpecifications` map on that element's type entry in [`content-system-element-types.json`](../../Layout/Type/docs/introspection.md) (`bindingSpecifications[element.component]`) — the ids from the [Binding specifications](../../Binding/docs/introspection.md) fold that a client may pass as `bindingSpecificationId` to a bind-element action.

`diagnostics.wellFormed` is true when there are no intrinsic-scope error violations (the persistence gate predicate); `diagnostics.resolvable` is true when there are no binding-scope error violations (the serving gate predicate, meaningful only when a source was bound). Each violation derives its `scope` and `severity` from its `code`:

| `code`                   | `scope`   | `severity` |
|--------------------------|-----------|------------|
| `unregistered_component`     | intrinsic | error      |
| `duplicate_element_id`       | intrinsic | error      |
| `invalid_config`             | intrinsic | error      |
| `mismatched_reference_type`  | intrinsic | error      |
| `unknown_style_option`       | intrinsic | error      |
| `orphaned_provider`          | intrinsic | warning    |
| `unresolved_required`        | binding   | error      |
| `ambiguous_required`         | binding   | error      |
| `broken_required_chain`      | binding   | error      |
| `unfilled_required_input`    | binding   | error      |
| `unresolved_optional`        | binding   | warning    |

`mismatched_reference_type` flags a stored reference wiring (any `dataRequirements` entry the element carries, not only one recorded in `attributedSpecifications`) whose resolved produced type is not assignable to the property's declared FQCN. It is intrinsic, not binding-scope: the mismatch is a property of the element's own stored wiring, independent of any bound `rootSource`. A config that fails to resolve (a client defect) is `invalid_config` instead; a config that resolves and fits produces no violation — it becomes a `stored` resolution instead (see the `origin` note above).
