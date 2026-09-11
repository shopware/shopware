# Migrate a base component without changing legacy overrides

This guide is for maintainers who migrate an Options component to an SFC.
Existing extensions can keep their Options API definitions and Twig block overrides.
The shim gives Vue the original Options definitions and bridges the SFC state to that instance.

A compatible migration retains public names, member categories, and observable behavior.
The build transform derives bridge metadata from ordinary SFC declarations.
Some combinations still need review; see the release checks below.

## 1. Retain the public names and Options categories

List every public data field, computed property, and method, including inherited mixin members.
Keep members that the base does not use: an extension can still use them.
Preserve arguments, return values, writable computed setters, and observable behavior.
These are part of the [Administration compatibility rules](https://developer.shopware.com/docs/resources/guidelines/code/backward-compatibility.html#administration).

For a base with `count` in `data`, `doubled` in `computed`, and `read` in `methods`:

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';

const count = ref(1);
const doubled = computed(() => count.value * 2);
function read() {
    return doubled.value;
}

swDefinePublic({ count, doubled, read });
</script>
```

Use `computed({ get, set })` for a computed property that previously had a getter and setter.
The shared Vite and Jest transform derives data, computed, and method categories from public declarations.
It adds bridge metadata to compiled output; neither the codemod nor handwritten SFCs need compatibility options.
A function stored inside a ref remains data.
The bridge uses the generated categories to preserve Vue's Options initialization order and introspection.
Do not use a plain copied value where a reactive binding is required.

Prefer the original names. If a local rename is unavoidable, retain a public alias.
The transform resolves aliases that share the original ref, object, or function:

```ts
const count = ref(1);
const oldCount = count;
swDefinePublic({ oldCount });
```

Keep the old name in `swDefinePublic` too, so the declared public surface remains clear.
Test the alias from an override, a parent template ref, and the component template.

## 2. Use only complete composable mappings

The codemod emits ordinary declarations and retains unused mapped members in compatibility mode.
The build transform also recognizes methods from the verified composable mapping.
Its descriptor must have `legacyCompatible: true` before a mapping can pass the gate.
Currently, only `placeholder` has this verification.

An arbitrary imported value or factory result does not reveal its original Options category.
The transform leaves unknown expressions on the runtime fallback instead of guessing their category.
Such bases need a reviewed mapping or must remain on Options; automatic inference is not a compatibility guarantee.

For another mapping, verify every inherited member, prop, event, callback, and lifecycle effect.
A composable must call the overrideable host member when the old mixin did so.
Returning a method does not redirect calls that the composable makes through its own closure.
Do not run the old mixin alongside its replacement; that can duplicate state and effects.

Leave a base on Options when the mapping is incomplete or changes initialization order.
The batch runner rejects base `created()` hooks and watchers for this reason.
A `full` conversion result means a candidate passed the static checks; it does not certify runtime compatibility.

## 3. Check the complete migration before replacing the base

- Compare the migrated component with the current Options component using the same legacy override.
- Exercise `$super`, immediate watchers, computed setters, and internal base calls to overridden methods.
- Read and write public members through a parent ref. Include members added by the extension and reads through `$parent`.
- Compare `$data` and `$options.methods` / `$options.computed` when extensions inspect them.
- Preserve an override's `expose` list, including an empty list. Check the parent-facing API separately from internal access.
- Retain block names and scope, slots, refs, props, events, imports, and functional CSS selectors.
- Keep the original entry point when any required behavior differs.

The deeper checks leave these migration conditions:

| Pattern | Current behavior | Action for a compatible release |
| --- | --- | --- |
| Component identity used across hooks and ordinary methods/computed | Members without `$super` keep Vue's receiver. Members that reference `$super` still use a layer-specific receiver to preserve calls after `await`. | Check identity-dependent code in `$super` members. Keep the base on Options if identity must stay equal. |
| Base state read before Options initialization | Categories inferred from recognized public declarations preserve native publication order, including shared aliases. | Check imported values and custom factories whose original category cannot be inferred. Keep the base on Options until its mapping is verified. |
| Calling `$options.data()` to restore defaults | The generated data factory returns current setup refs, so this does not recreate the original defaults. Ordinary `Object.assign(this.$data, freshValues)` works. | Retain the original data factory in a dedicated migration or keep the base on Options. A snapshot cannot reproduce dynamic defaults. |
| Assigning a future data key in `beforeCreate`, then reading it in `data()` | This edge can retain Vue context access that exposes the underlying ref. | Keep the base on Options if this sequence is required. Do not move extension code merely to make the test pass. |

Parent refs also use Vue's separate exposed object. Equality between that object and a lifecycle receiver is not preserved.
The static gate does not detect those patterns in unknown extensions.
Do not treat a clean plugin search as proof that they cannot occur.
If the public behavior cannot be retained, schedule that base migration for a major release with an explicit upgrade path.

See the [codemod instructions](../../scripts/codemods/sfc-migration/README.md) for dry runs and draft generation.
