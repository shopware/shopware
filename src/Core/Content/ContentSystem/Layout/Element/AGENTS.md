@README.md

## Constraints

- Property getters return null for missing keys — never throw. Use `hasProperty()` or null coalescing
- `allSlotElements()` is a generator — do NOT convert with `iterator_to_array()`
- `replacePlaceholders()` is recursive and only replaces scalar values
- Don't use `assign()` from `AssignArrayTrait` — corrupts the struct/non-struct property split
- Slots: `array<string, SlotContent>`, multiple elements per slot
