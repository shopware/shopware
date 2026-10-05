## Constraints

- Never reference `Shopware\Storefront` from Core. Check: does any class under `src/Core/Framework/ContentSystem` reference it? See [README.md](README.md#key-classes).

## Where to look

- Assignment validator, root-source read, `null` root source, skip state: [README.md](README.md#key-classes)
- Immutable root source and the creating write: [stored-model.md](../../../Core/Framework/ContentSystem/docs/principles/stored-model.md#the-creating-write-sets-the-root-source-of-a-layout)
