# Resolve-and-Diagnose Response

`DiagnoseResponse` is a sibling `\JsonSerializable` value object that normalizes through `LayoutDiagnosticsResultNormalizer`. Same output-only discipline as [`MutationResponse`](mutation-response.md): serialized to the response and discarded, never cached or denormalized.

`200 OK` with `{ resolutions, diagnostics }`.

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

`resolutions` is keyed by element id. Each entry is the list of that element's declared properties with how each is (or is not) filled, and encodes as `{}` when empty (never `[]`). `kind` is `primitive` or `reference`; a `reference` property carries a `resolved` candidate (or `null`) and the full `candidates` list. A candidate's `origin` is `parent` (an ancestor provider, `providerElementId` naming the providing element). `root` is a root-ambient offer or an applied root-scoped mapping, including a non-root plugin source (`providerElementId` is null because no ancestor supplies it). `loader` is a data loader. `stored` is the element's own applied wiring: a stored reference wiring whose produced type resolves and is assignable to the declared FQCN, filling `resolved` directly when no mapping takes precedence, never a `candidates` menu entry. A `stored` candidate is not loader-shaped: its `loaderSource`, `configTemplate`, and `configComplete` all serialize as `null` (clients branch on `origin` before reading them).

A client derives the specifications applicable to an element from the `bindingSpecifications` map on that element's type entry in [`content-system-element-types.json`](../../Layout/Type/docs/introspection.md) (`bindingSpecifications[element.component]`): the ids from the [Binding specifications](../../Binding/docs/introspection.md) fold that a client may pass as `bindingSpecificationId` to a bind-element action. It is a per-element-type catalog lookup, not a resolution against an element's actual wiring or ancestry.

`diagnostics.wellFormed` is true when there are no intrinsic-scope error violations (`LayoutGate::wellFormedness()`). `diagnostics.resolvable` is true when there are no binding-scope error violations (`LayoutGate::resolvability()`). A bound source enables all binding checks; unbound drafts can still report inline-token findings as described [below](#mapping-findings). Each violation derives its `scope` and `severity` from its `code`:

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
| `invalid_mapping`            | binding   | error      |
| `unresolved_optional`        | binding   | warning    |

`mismatched_reference_type` flags a stored reference wiring (any `dataRequirements` entry the element carries, not only one recorded in `attributedSpecifications`) whose resolved produced type is not assignable to the property's declared FQCN. It is intrinsic, not binding-scope: the mismatch is a property of the element's own stored wiring, independent of any bound `rootSource`. A config that fails to resolve (a client defect) is `invalid_config` instead. A config that resolves and fits produces no violation. It becomes a `stored` resolution instead (see the `origin` note above).

`mismatched_property_type` flags a stored `properties` value that disagrees with the primitive type the element's type declares for that key, one violation per key, with `key` carrying the property name. `string` takes a string, `integer` an integer, `number` an integer or a float, `boolean` a boolean, and a union whose members are all primitive takes a value matching any member. `null` is accepted too, because whether a key may be absent or null is `unresolved_required`'s concern. A key declared as `object`, as an FQCN, or as a union carrying either constrains nothing and is never flagged. Nor is a `resolvedBy` storage key the type does not also declare as a property. Like `unknown_style_option`, it never appears on a DAL write: the write-path constraint pass refuses such a tree before the gate that produces these diagnostics runs.

## Mapping findings

`invalid_mapping` flags a stored data mapping the bound source's candidate catalogue does not admit, with `key` carrying the mapped property. This includes a property without `mappable`, an unavailable path, context cardinality different from the candidate's, or a candidate value type that cannot fill the declared property. It has binding scope because admission depends on the root source's catalogue. Without a `rootSource`, whole-field mappings remain unjudged, but explicit inline tokens in opted-in properties still produce `invalid_mapping` violations because inline mapping requires a bound source.

The same conditions refuse the `content_layout` write, where they surface as `CONTENT_SYSTEM__PROPERTY_NOT_MAPPABLE`, `CONTENT_SYSTEM__UNKNOWN_MAPPING_PATH`, `CONTENT_SYSTEM__MAPPING_CONTEXT_TYPE_MISMATCH`, or `CONTENT_SYSTEM__MAPPING_TYPE_MISMATCH` on the property path.

For a mapped primitive or reference property, `resolved` reports the configured mapping with `origin: "root"` and its typed display name in `contextKey`, such as `product.cover` or `AcmePlugin:image.media`. A mapped reference takes precedence over saved picker wiring and suggested context/loader candidates. This is source-selection metadata: invalid mappings still produce `invalid_mapping`, no source values are inspected, and the required-primitive rule remains unchanged.
