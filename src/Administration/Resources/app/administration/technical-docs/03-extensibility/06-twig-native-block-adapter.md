# Twig overrides on native SFC blocks

Existing `Shopware.Component.override()` registrations can use Twig blocks when the base component uses native SFC blocks. The adapter preserves the host state and Vue rendering context.

## Registration and lookup

The component factory parses Twig block registrations and assigns their order before asynchronous loaders finish. Entries are sorted by override priority and registration order. Runtime lookup includes the host component and its inheritance lineage, so an unrelated component with the same block name does not receive the override.

Mounted blocks subscribe to index changes. Stable entries retain their renderer and cache; obsolete entries release their resources. A late change to another block does not replace an existing input.

## Rendering and state

`create-shim-slot.ts` compiles the reconstructed Vue template and invokes it with the host's rendering context. Each block instance has its own render cache and writable scope.

The scope resolves lexical loop and slot bindings, component state, then host APIs. Consequently, Twig content can use `v-model`, computed setters, `$emit`, `$attrs`, `$slots`, `$refs`, local components, and directives. Template refs belong to the host component.

The SFC compiler forwards destructured loop and scoped-slot variables through block boundaries. A local variable shadows a component field. Local aliases cannot be rebound; writes to their object members still reach the original object.

Nested reconstructed blocks receive the same data scope. Repeated `{% parent %}` calls render the same predecessor independently. Parent rendering does not consume a shared stack.

## Blocks around named slots

Preserve the block's original position when migrating a receiver:

```vue
<SlotReceiver>
    <sw-block name="receiver_slots">
        <template #content="{ label }">{{ label }}</template>
        <template #footer>Footer</template>
    </sw-block>
</SlotReceiver>
```

The compiler removes this structural wrapper and merges slot definitions into the original receiver VNode before mount or update. The receiver keeps its props, listeners, ref, and identity. Vue compiles scoped slots, conditional slots, and dynamic slot names.

A Twig override can retain the preceding slot set and replace one slot:

```twig
{% block receiver_slots %}
    {% parent %}
    <template #content="{ label }">
        <strong>{{ label }}</strong>
        {% parent %}
    </template>
{% endblock %}
```

The outer parent retains preceding slot definitions. The parent inside `#content` renders that slot's preceding content with the current slot props. Omitting the outer parent replaces the slot definitions owned by the block.

The codemod preserves this placement. It does not move the block inside a slot, which would change the extension point. A slot definition still needs a receiving component.

## Conditional chains

The adapter preserves the existing bridge for Vue conditionals that continue across native and Twig blocks. Generated helpers track default, Twig, and native cases in render order. Twig rendering evaluates synchronously and reserves its cases before execution. Cleanup releases condition state when its renderer is removed.

Ordinary adjacent `v-if` / `v-else` content remains ordinary Vue template syntax. A conditional override without a host scope reports the missing `:data="$dataScope"` binding.

## Build integration

Both Administration and extension Vite builds run the shared SFC transform before Vue compilation. Post transforms prepare direct-import component definitions and merge structural slot VNodes. The Jest transformer uses the same slot transform and composes its source map. Vue hot updates resolve the prepared definition before reload or rerender, retaining legacy options and custom rendering.

The integration fixture builds production assets, reads their written source maps, and transforms an SFC through the development server. Source locations resolve to authored files.

## Migration boundaries

The adapter requires retained component and block names. It cannot infer a removed extension point or recreate changed component behavior. Native override blocks retain their own authoring rules; structural slot support here bridges existing Twig overrides.

Twig is used to parse block structure. Arbitrary Twig control flow is not evaluated against live component state. The old template factory evaluated Twig with an empty context, while Vue directives evaluate host state. Review compile-time Twig logic separately when migrating a component; use Vue directives for runtime conditions and loops.

Late template replacement can legitimately change DOM structure. DOM identity is retained when the same effective content remains. Component-definition options must register before Vue initializes the host.

See [Options compatibility](./04-composition-extension-system.md#options-api-shim) and [native setup authoring](./07-native-setup-authoring.md).
