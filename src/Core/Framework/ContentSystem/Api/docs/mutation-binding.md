# Applying a Binding Through a Mutation

`bind-element` applies `bindingSpecificationId`'s wiring onto `elementId`, as described in the [BindElement operation](../../Mutation/docs/operations.md#bindelement).
An unregistered id (`bindingSpecificationNotFound`), a specification whose declared `type` does not match the element's `component` (`bindingTypeMismatch`), and a `!scoped` config value with no entry for the layout's root source or no root source (`bindingRootSourceNotScoped`) are rejected with `400`.

`insert-element` accepts the same optional `bindingSpecificationId`.
When given, the named specification's wiring is applied onto the freshly scaffolded element atomically after scaffold, by the same `Binding/BindingApplicator` merge as `bind-element`, in one edit.
The specification is resolved before any tree change.
An unregistered id (`bindingSpecificationNotFound`) or a specification whose declared `type` does not match the inserted `type` (`bindingTypeMismatch`) is rejected with `400` and nothing is inserted.
A `!scoped` config value of the named specification with no entry for the root source (or no root source) is rejected the same way, as `bindingRootSourceNotScoped`, with a null `elementId`, because the scaffold's id is minted by the server.

**Automatic default application.**
`insert-element` and `replace-element` fill-apply the type's default specification, and `attach-element` does so onto every element of the attached subtree, with no client action and no `bindingSpecificationId` in the request.
A type whose default set holds more than one specification makes the mutation throw a `409` (`bindingSpecificationDefaultAmbiguous`).
How the default is selected (zero, one, more than one) is in [default-specification.md](../../Binding/docs/default-specification.md).
How it layers under an explicit `bindingSpecificationId` and under carried wiring is in [applying.md](../../Binding/docs/applying.md).
