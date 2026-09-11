# Migrate a base component without changing legacy overrides

This guide is for maintainers who migrate an Options component to an SFC.
Existing extensions can keep their Options API definitions and Twig block overrides.
The shim gives Vue the original Options definitions and bridges the SFC state to that instance.

The earlier “missing 3%” described two checklist conditions, not a percentage of affected extensions.
Both conditions belong to the base migration: retain public names and retain their Options categories.
The deeper audit also found unresolved instance-identity and initialization differences; see the release checks below.

## 1. Retain the public names and Options categories

List every public data field, computed property, and method, including inherited mixin members.
Keep members that the base does not use: an extension can still use them.
Preserve arguments, return values, writable computed setters, and observable behavior.
These are part of the [Administration compatibility rules](https://developer.shopware.com/docs/resources/guidelines/code/backward-compatibility.html#administration).

For a base with `count` in `data`, `doubled` in `computed`, and `read` in `methods`:

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';

// The bridge uses these categories when it builds $data and $options.
defineOptions({
    legacyOptionsMembers: {
        count: 'data',
        doubled: 'computed',
        read: 'method',
    },
});

const count = ref(1);
const doubled = computed(() => count.value * 2);
function read() {
    return doubled.value;
}

swDefinePublic({ count, doubled, read });
</script>
```

Use `'writable-computed'` for a computed property that previously had a getter and setter.
Keep the setter in `computed({ get, set })` as well.
The metadata describes behavior; it does not create a missing setter or method.
Do not use a plain copied value where a reactive binding is required.

Prefer the original names. If a local rename is unavoidable, `legacyOptionsBindings` maps the old instance name to the new setup binding:

```ts
defineOptions({
    legacyOptionsBindings: { oldCount: 'count' },
    legacyOptionsMembers: { oldCount: 'data' },
});
const count = ref(1);
const oldCount = count;
swDefinePublic({ oldCount });
```

Keep the old name in `swDefinePublic` too, so the declared public surface remains clear.
Test the alias from an override, a parent template ref, and the component template.

## 2. Use only complete composable mappings

The codemod writes the compatibility metadata and retains unused mapped members in compatibility mode.
Its descriptor must have `legacyCompatible: true` before a mapping can pass the gate.
Currently, only `placeholder` has this verification.

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
- Retain block names and scope, slots, refs, props, events, imports, and functional CSS selectors.
- Keep the original entry point when any required behavior differs.

Two shim differences remain unresolved after the deeper audit:

| Pattern | Current difference | Action for a compatible release |
| --- | --- | --- |
| Component identity used as a `Map` / `WeakMap` key or compared across hooks and methods | Method and computed wrappers use a different receiver from lifecycle hooks. | Keep the base on Options until the bridge preserves identity. Do not require existing extensions to replace their keys. |
| Base state read in `beforeCreate` or an override's data factory | Setup state is visible before native Options would publish the merged data. | Keep the base on Options when this changes behavior. Moving extension code to `created` is not a transparent fix. |

The static gate does not detect those patterns in unknown extensions.
Do not treat a clean plugin search as proof that they cannot occur.
If the public behavior cannot be retained, schedule that base migration for a major release with an explicit upgrade path.

See the [codemod instructions](../../scripts/codemods/sfc-migration/README.md) for dry runs and draft generation.
