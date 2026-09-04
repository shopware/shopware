# Persisted Mutation Errors

The failure conditions specific to the persisted mutation actions ([persisted-mutation.md](persisted-mutation.md)).

In addition to the structural `400`s of the stateless endpoints (`mutationTargetNotFound`, `mutationCycle`, `mutationSlotRequired`, `mutationInvalidWrapTargets`, `mutationUnknownType`, `bindingSpecificationNotFound`, `bindingTypeMismatch`, `#[MapRequestPayload]` validation):

| Condition                                                                         | HTTP | Factory / source                                                                                                                            |
|-----------------------------------------------------------------------------------|------|---------------------------------------------------------------------------------------------------------------------------------------------|
| `{layoutId}` names no stored layout                                               | 404  | `contentLayoutNotFound`                                                                                                                     |
| `expectedVersion` does not match the layout's current `updatedAt`                 | 409  | `layoutVersionConflict` (no write)                                                                                                          |
| `insert-element` or `replace-element` on a type whose default binding specification set holds more than one (only reachable via a database row created outside the app lifecycle) | 409 | `bindingSpecificationDefaultAmbiguous` (thrown before the repository write, so nothing is persisted)                                       |
| `expectedVersion` is not a parseable date-time                                    | 400  | `invalidVersionToken` (no write)                                                                                                            |
| The committed edit breaks resolvability for a bound source, or is not well-formed | 400  | `ContentLayoutWriteValidator` rejects the `content_layout` write (`WriteException`); the binding-scope violations ride in the error payload |

Detached content (`orphaned`), dropped wiring (`droppedWiring`), and dropped property values (`droppedProperties`) are **committed-out and reported**, never rejected: the stored tree omits them and the response hands them back, so the caller re-places subtrees with `attach-element`, re-wires the keys, or re-applies the values. The new type structurally has no home for dropped wiring, and no operation edits a surviving element's wiring.
