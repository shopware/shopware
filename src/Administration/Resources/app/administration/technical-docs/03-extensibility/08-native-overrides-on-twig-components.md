# Native Overrides on Twig Components

Counterpart to the [Twig → Native Block Runtime Adapter](./06-twig-native-block-adapter.md): that adapter keeps _legacy Twig overrides_ working on _migrated_ components, this one lets _native SFC overrides_ target components that are _not migrated yet_.

Both halves activate automatically. Nothing changes for override authors, and the same override file keeps working unchanged once its target is migrated.

---

## Problem

A native override declares its target with `<sw-block extends="…">` and replaces state with `swDefineOverride({ … })`. Both need the target to be a native setup component: the block needs a matching `<sw-block name="…">`, the state needs an extendable setup wrapper. A component still rendered through TwigJS has neither, so an override against it used to do nothing at all, and without an error.

Plugin authors therefore had to wait for core to migrate a component before they could write an override for it.

---

## Template half

**The override announces its target blocks at build time.** The setup transform emits a plain `<script>` into every override SFC:

```js
Shopware.Component.registerNativeExtensionTargets?.({
    component: 'sw-dashboard-index',
    blocks: ['sw_dashboard_index_content_intro_welcome_message'],
});
```

A plain `<script>` runs at module evaluation, during `loadPlugins()`, while `<script setup>` would only run on mount. The block names therefore land in the registry _before_ templates are resolved. The call is optional (`?.`) because it is compiled into every shipped plugin bundle: a missing function would abort the entry module and take the whole plugin down with it.

**The template factory wraps the matching blocks.** `wrapNativeBlockTargets` inserts an extension point around every block that is a registered target:

```twig
<div class="sw-example">
    {% block sw_example_title %}<h1>{{ title }}</h1>{% endblock %}
    {% block sw_example_body %}<p>{{ body }}</p>{% endblock %}
</div>
```

With `sw_example_body` registered as a target, the rendered template becomes:

```html
<div class="sw-example">
    <h1>{{ title }}</h1>
    <sw-block name="sw_example_body" :data="$dataScope" :sw-internal-legacy-shim="false"><p>{{ body }}</p></sw-block>
</div>
```

`sw_example_title` is not a target and leaves no trace, exactly as before, because Twig block markers never reach the output. Only the registered block gains an element. (Whitespace is normalised here for readability; the transform does not reformat.)

Wrapping happens **after** Twig overrides are merged and only for the render. The stored token tree stays untouched, because components inheriting it would otherwise inherit the wrapper too. The legacy shim is switched off because those overrides are already part of the merged content; leaving it on would feed the same override in a second time.

### Blocks that open with a slot template

Roughly one in six blocks starts with a named slot template. Wrapping such a block from the outside would bind the slot to `sw-block`, which renders only its default slot, so the content would disappear without an error. The extension point is therefore placed _inside_ the template:

```twig
<sw-card>
    {% block sw_example_header %}<template #header><b>{{ title }}</b></template>{% endblock %}
</sw-card>
```

```html
<sw-card>
    <template #header
        ><sw-block name="sw_example_header" …><b>{{ title }}</b></sw-block></template
    >
</sw-card>
```

The slot stays on `sw-card`, and the override replaces the slot's content.

This works when the block consists of exactly one slot template spanning its whole content. Several sibling slot templates, or a slot template next to other content, have no single position that serves every part; those blocks are left unwrapped and a warning names them. **15 of 8292 blocks** fall into this category, mostly `*_content` blocks of list pages that fill two slots of their `sw-page` at once.

---

## Setup half

`swDefineOverride` replacements are applied by an adapter that works around one ordering constraint: only a `setup()` return value outranks `data` and `computed` in Vue's instance proxy, but `setup()` runs before either of them exists.

The adapter therefore **reserves the slot early and fills it late**:

1. `setup()` returns an empty object. Vue keeps a live reference to it as the component's setup state, the entry with the highest precedence.
2. A `created()` hook, injected as the first mixin, runs the override callbacks. At that point `data` and `computed` exist, so `previousState` can read them.
3. The results are written into the object from step 1, before the first render.

`previousState` is a proxy that serves every key as a ref-like accessor, so `previousState.x.value` works for `data`, `props`, `computed` and `methods` alike, including calling a replaced method's original. It deliberately reads _past_ the setup state, because the override's own result already lives there and reading it would loop.

Writing back (`previousState.x.value = …`) lands in the base state, not in the override's result. Props stay read-only, as they are on Vue's own proxy.

---

## Limits

**Blocks that mix slot templates with other content** cannot host an extension point; see above.

**An `immediate` watcher on an overridden key fires once with the base value.** Vue sets watchers up before any `created` hook, so the first run happens before the override exists. Every later evaluation sees the override.

**The adapter relies on undocumented Vue behaviour**: that a setup result stays live and that keys added later are honoured. It is pinned to the Vue version in `package.json`, and a canary test fails loudly if an upgrade changes it.

---

## Lifetime

Both halves become unnecessary once every override target renders natively. They are annotated `@experimental stableVersion:v6.9.0 feature:ADMIN_COMPOSITION_API_EXTENSION_SYSTEM`, which by convention means: stable or removed by then.

`registerNativeExtensionTargets` is the exception. It is compiled into shipped plugin bundles, so its signature is a runtime contract with every plugin already in the market. Adding fields stays compatible, renaming or removing it does not.
