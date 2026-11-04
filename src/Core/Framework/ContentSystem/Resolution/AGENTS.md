## Constraints

- Keep `ElementResolver::pickDefault()` conservative: a `Root` candidate outranks a `Parent` candidate, ambiguous candidates select none. Check: does an ambiguous pool still return `null`? [context-wiring.md](../docs/principles/context-wiring.md#context-flows-only-between-adjacent-elements)
- Change `AvailableContextResolver::resolve()` only with the runtime delivery it mirrors. Check: does it still compute provider keys through `ProviderDeliveryKeyResolver`, as serving does? [context-wiring.md](../docs/principles/context-wiring.md#the-write-gate-computes-each-delivery-rule-it-checks-exactly-as-serving-computes-it)
- Use shared mapping admission for reference-provider backing with the explicit root source. Never read provider values during static resolution or fall back to unrelated ambient/loader backing for a rejected mapping. [applied mappings](README.md#applied-mappings)

## Where to look

- Default selection among candidates, config-complete loaders, `Stored` requirements as applied wiring, what `PropertyResolution` holds, `resolve()` for an unknown component type: [README.md](README.md#candidate-selection)
- Available context per element, its root context per section, empty results, omitted primitive providers: [README.md](README.md#available-context)
- Applied mappings as selected `Root` resolutions and effective-type provider backing: [README.md](README.md#applied-mappings)
