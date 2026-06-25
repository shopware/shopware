---
name: shopware-storefront-component
description: >-
  Create or migrate Shopware Storefront Twig UX components following all best practices.
  Use when creating new storefront components, migrating legacy Twig templates or JS plugins
  to the new component system, adding JavaScript interactivity with ShopwareComponent,
  working with files in views/components/ directories, or when asked about storefront
  components, twig components, ShopwareComponent, CVA, or the component system.
---

# Shopware Storefront Components

Available since Shopware 6.7.11.0. Components live in `views/components/` and combine Symfony UX Twig Components with Shopware-specific SCSS and JS handling.

## Directory structure

```
views/components/
  Namespace/
    ComponentName.html.twig       # required
    ComponentName.scss            # optional
    ComponentName.js              # optional
    ComponentName.stories.json    # optional (Storybook)
```

Or index-based (all files in a subdirectory, same component name):

```
views/components/Namespace/ComponentName/
    index.html.twig
    index.scss
    index.js
```

Both produce `<twig:Namespace:ComponentName />`.

**Naming rules:**
- Component names are UpperCamelCase
- Shopware core uses `Sw` namespace: `<twig:Sw:Button>`
- Extensions use bundle name: `<twig:MyPlugin:Button:Primary>`
- Cannot mix index naming with PHP class components

## Twig template

```twig
{# views/components/Sw/ProductCard.html.twig #}

{% props
    product,                                            {# required — no default #}
    layout = 'default',                                 {# optional with default #}
    allowBuyAction = config('core.cart.wishlistEnabled'), {# feature flags as props #}
    slots = [],                                         {# always include for slot support #}
%}

{% set cardCVA = cva({
    base: 'sw-product-card card',
    variants: {
        layout: {
            default: 'is--layout-default',
            horizontal: 'is--layout-horizontal',
        },
    }
}) %}

{% set attributeDefaults = {
    class: cardCVA.apply({ layout }),
} %}

<div {{ attributes.defaults(attributeDefaults) }}>

    {% block media %}
        <twig:Slot name="media">
            {# default media content #}
        </twig:Slot>
    {% endblock %}

    <div {{ attributes.nested('body').defaults({ class: 'sw-product-card__body card-body' }) }}>

        {% block title %}
            <twig:Slot name="title">
                <h2 class="sw-product-card__title">{{ product.translated.name }}</h2>
            </twig:Slot>
        {% endblock %}

        {% block content %}{% endblock %}

    </div>

</div>
```

### Props
- camelCase, with defaults wherever possible
- Read feature flags via `config()` as default values — never inline in template logic
- Never rely on parent context or global Twig variables directly

### Blocks
- Only for logical sections: `media`, `title`, `price`, `actions`, `content`
- Don't prefix block names with the component name — they are auto-namespaced
- Don't wrap every element speculatively; blocks must have a defined purpose
- Button-like components must include `{% block content %}{% endblock %}` as a default slot

### Slots
- Use verbose syntax only — never self-closing:
  ```twig
  {# ✅ correct #}
  <twig:Slot name="media"></twig:Slot>

  {# ❌ wrong #}
  <twig:Slot name="media" />
  ```
- Always declare `slots = []` prop on components that expose slots
- Combine slots with blocks for both programmatic (Twig override) and CMS extension:
  ```twig
  {% block media %}
      <twig:Slot name="media">
          {# default content #}
      </twig:Slot>
  {% endblock %}
  ```

### Attributes & CVA
- Always use `attributes.defaults({})` on the root element — never hardcode HTML attributes
- Use `attributes.nested('child-name')` for child elements
- CVA must always have a `base` key; `variants` map to CSS modifier classes

### With JS interactivity — add to `attributeDefaults`:
```twig
{% set jsOptions = { layout } %}

{% set attributeDefaults = {
    class: cardCVA.apply({ layout }),
    'data-component': 'Sw:ProductCard',
    'data-component-options': jsOptions|json_encode,
} %}
```

## JavaScript component

```js
// ComponentName.js — no imports needed, ShopwareComponent is global

export default class ProductCard extends ShopwareComponent {

    static options = {
        layout: 'default',
    };

    init() {
        this.el.addEventListener('click', this.onClick.bind(this));
    }

    destroy() {
        // Always clean up listeners
        this.el.removeEventListener('click', this.onClick.bind(this));
    }

    onClick(event) {
        window.Shopware.emit('ProductCard:Click', { element: this.el });
    }
}
```

- `this.el` — root DOM element the component is attached to
- `this.options` — static defaults merged with `data-component-options`
- Automatic init on all matching `[data-component]` elements, including dynamically added ones
- No bundling or imports required

**Event system:**
```js
// Emit
window.Shopware.emit('MyComponent:Action', payload);

// Subscribe
window.Shopware.on('MyComponent:Action', (payload) => { ... });

// Interceptable event (allows extensions to modify data before processing)
// Emitter:
const { data } = window.Shopware.emitInterception('MyComponent:PreSubmit', { data });

// Interceptor in extension:
window.Shopware.intercept('MyComponent:PreSubmit', (payload) => {
    payload.data.extra = 'value';
    return payload; // must return
});
```

**Mutation observation** (opt-in):
```js
init() {
    this.initializeObserver({ childList: true, attributes: true, subtree: true });
}
onContentUpdate(mutationRecord) { ... }
onAttributeUpdate(mutationRecord) { ... }
```

## SCSS

```scss
// ComponentName.scss

.sw-product-card {
    // Override Bootstrap via its CSS variables — never hardcode values Bootstrap owns
    --bs-card-spacer-y: 1rem;
    --bs-card-border-color: var(--bs-border-color);

    // Own CSS custom properties — always sw- prefix
    --sw-product-card-media-height: 200px;
}

// Flat BEM — no nesting
.sw-product-card__media {
    height: var(--sw-product-card-media-height);
}

.sw-product-card__title {
    font-size: $font-size-lg;
}

// CVA modifier classes
.sw-product-card.is--layout-horizontal {
    .sw-product-card__body { flex-direction: row; }
}
```

**Rules:**
- Root class: `sw-{component-name}`, children: `sw-{component-name}__{element}`
- Prefer CSS custom properties over SCSS variables for runtime-customisable values
- No deep selector nesting — flat BEM selectors
- Override Bootstrap via `--bs-*` variables, not by targeting Bootstrap selectors directly
- **SCSS variables: never guess variable names.** Before writing any `$variable`, look it up:
  - **`$sw-*` theme variables** (including any added by the active theme): read `var/theme-variables.scss` — auto-generated by the ThemeCompiler, always reflects the current theme
  - **Bootstrap-derived variables** (`$font-size-*`, `$spacer-*`, `$gray-*`, etc.): read `src/Storefront/Resources/app/storefront/src/scss/skin/shopware/abstract/variables/`
  - If a variable is not found in either location, use a CSS custom property (`--sw-*`) instead — it is always safe and runtime-customisable

## Theme registration

Component files are **not** included automatically — they must be referenced in `theme.json`:

```json
{
    "style":  ["@Storefront", "@Components"],
    "script": ["@Storefront", "@Plugins", "@Components"]
}
```

Specific file: `"@Components:MyPlugin/Button/Primary.scss"` (namespaced: `"@Components:MyPlugin/Button/Primary.scss"`)

## PHP class component (plugins only)

Only when PHP business logic is genuinely needed. Prefer anonymous (Twig-only) components.

```php
// views/components/Button/Primary.php
#[AsTwigComponent()]
class Primary
{
    public string $label = 'Click me!';
}
```

Register in `services.php`:
```php
$services->set(Primary::class)->autoconfigure(true);
```

## Stories (Storybook)

```json
{
    "title": "Sw/ProductCard",
    "parameters": {
        "server": { "id": "Sw:ProductCard" },
        "template": "<twig:Sw:ProductCard :product=\"product\" />",
        "slots": [
            { "name": "media", "description": "Product image slot." }
        ]
    },
    "argTypes": {
        "layout": { "control": "select", "options": ["default", "horizontal"], "description": "Card layout." }
    },
    "stories": [
        { "name": "Default", "args": { "layout": "default" } }
    ]
}
```

## Migrating legacy templates

When converting a `storefront/component/*.html.twig` or JS plugin:

1. Create `views/components/Namespace/ComponentName.html.twig`
2. Convert Twig `{% block %}` tree into `{% props %}` + focused blocks on logical sections
3. Add `attributes.defaults({})` on root, replace hardcoded classes with CVA
4. Replace `data-plugin="true"` with `data-component="Namespace:ComponentName"`
5. Move SCSS from `app/storefront/src/scss/` to component directory; flatten nesting to BEM
6. Convert JS plugin class (`PluginBaseClass`) to `ShopwareComponent` subclass (`init`/`destroy`)
7. Register `@Components` in the theme's `theme.json` style and script arrays

## Checklist

- [ ] Files in `views/components/Namespace/` with consistent names
- [ ] `{% props %}` at top; `slots = []` included on slottable components
- [ ] Root element uses `attributes.defaults({})` — no hardcoded HTML attributes
- [ ] CVA with `base` used for class variants
- [ ] Blocks only for meaningful logical sections
- [ ] Slots use verbose `<twig:Slot name="..."></twig:Slot>` syntax
- [ ] JS: `destroy()` removes all event listeners; no imports
- [ ] SCSS: flat BEM, `sw-` CSS custom props, Bootstrap vars used for overrides, all `$variables` looked up in `var/theme-variables.scss` or `src/Storefront/Resources/app/storefront/src/scss/skin/shopware/abstract/variables/` before use
