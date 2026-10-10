# Mutation Response

The response body every stateless draft mutation action ([mutation.md](mutation.md)) returns.

`MutationResponse` is a `\JsonSerializable` value object (`@final`, `#[Package('framework')]`, not a service), built per request by both mutation controllers via a private constructor plus a `fromResult(MutationResult, StoredElementCodec)` factory: `layout` and `orphaned` are serialized through `StoredElementCodec::encode()`, `resolutions` and `diagnostics` through `LayoutDiagnosticsResultNormalizer`, and `jsonSerialize()` casts `resolutions` and `droppedProperties` to `(object)` when empty. It is output-only: serialized to the response and discarded, never cached, stored in a `SerializedField`, or denormalized with `StructNormalizer::denormalize()`.

`200 OK`, never persisted, never cached:

```json
{
  "layout": [ ... ],
  "resolutions": { "<elementId>": [ ... ] },
  "diagnostics": { "wellFormed": true, "resolvable": false, "violations": [ ... ] },
  "affectedElementIds": ["<elementId>"],
  "orphaned": [ ... ],
  "droppedWiring": ["<wiringKey>"],
  "droppedProperties": { "<propertyKey>": "<droppedValue>" }
}
```

| Field                | Notes                                                                                                                                                                                              |
|----------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `layout`             | The full edited tree, serialized the same way a stored layout is.                                                                                                                                  |
| `resolutions`        | Per-element resolutions, restricted to the affected elements. Same shape as the Resolve-and-Diagnose response (both routes share `LayoutDiagnosticsResultNormalizer`); encodes as `{}` when empty. |
| `diagnostics`        | The well-formedness / resolvability report, identical in shape to the Resolve-and-Diagnose response. The authoritative correctness output.                                                         |
| `affectedElementIds` | Elements whose resolution may have changed. A highlight hint for the editor; `diagnostics` is the authority.                                                                                       |
| `orphaned`           | Subtrees the edit detached (for example, a replace dropping the children of a slot the new type does not have), serialized as elements so the caller can re-place them.                            |
| `droppedWiring`      | Wiring keys the edit could not keep (for example, a replace to a type without that reference property), so the caller can re-wire.                                                                 |
| `droppedProperties`  | Static property values the edit could not carry to the new type (key absent, or a value the type rejects), keyed by property key, so the caller can re-apply them; encodes as `{}` when empty.     |

Nothing the edit detaches or drops is silently lost: it is always returned through `orphaned`, `droppedWiring`, or `droppedProperties`.
