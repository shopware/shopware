@README.md

## Constraints

- Property getters return null for missing keys — never throw. Use `hasProperty()` or null coalescing
- `allSlotElements()` is a generator — do NOT convert with `iterator_to_array()`
- `replacePlaceholders()` is recursive and only replaces scalar values
- Don't use `assign()` from `AssignArrayTrait` — corrupts the struct/non-struct property split
- Slots: `array<string, SlotContent>`, multiple elements per slot
- `properties` changes between stages: storage has static values only; post-hydration has static + loaded data merged
- Hydrator writes into properties via `setProperty($key, $data)` — same key as `data_requirements[$key]` and `accepts_context[$key]`
- After hydration, no distinction between static, loaded, and context-provided properties
- `jsonSerialize()` merges `structProperties` + `nonStructProperties` into one `properties` key
- Skeleton output (`ContentSkeletonElement`) strips properties entirely — only `id`, `component`, `slots`
