# Twig UX components

Guidelines to write uniform Twig UX components that follow our best practices and keep extensibility in mind.

## Anonymous components

* It is recommended to use anonymous components without a PHP class that are declared in twig.
* Components with a PHP class can only be used in plugins.
* Apps only support anonymous components.

## Naming and directory structure

* Each component name must be unique.
* Default storefront components are in the `Sw` namespace. `<twig:Sw:Button>`
* Component names are written uppercase.
* 3rd party components bring their own namespace e.g. `<twig:Agency:Button>`.
* (S)CSS and JavaScript are in the same directory as the component template.

```
views/components/Sw/
    Button.html.twig
    Button.js
    Button.scss
```

You can also use the index naming for anonymous components, which enables you to use the directory name as the component name. This can be helpful to have all component files in a separated directory, but without adding a duplicated namespace level. Make sure to name your JS and SCSS files accordingly.

```
views/components/Sw/Button
    index.html.twig
    index.js
    index.scss
```

Both of the above structures produces the component `Sw:Button`.


✅ Do:
```html
<twig:Sw:Button>My button</twig:Sw:Button>
```

❌ Don't
```html
<twig:sw-button>My button</twig:sw-button>
```

## Using a PHP class for the component

Besides the anonymous components, which only require a Twig template, you can also define your component via a PHP class which offers the possibility to add additional business logic to your component. You can have a look at the [official documentation](https://symfony.com/bundles/ux-twig-component/current/index.html) for all the details.

In Shopware we decided that these PHP classes should be placed right where your component template and other files of your component are located. This provides the experience of a real component system and you have all component related files in one place. Therefore you can simply add the PHP class to the described directory structure.

**Important Note:** If you use a PHP class for your component, you cannot use the anonymous component naming feature using `index.html.twig` for your component. The PHP class and the component template should have matching names.

```
views/components/Button/
    Button.html.twig
    Button.js
    Button.php
    Button.scss
```

The loading and template matching is already solved by placing the file in the right directory,
so you don't have to define a specific name or template path in your component class.

```PHP
<?php declare(strict_types=1);

namespace Shopware\Storefront\Resources\views\components\Button;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent()]
class Button
{
    public string $text = 'Click me!';
}
```

The only thing you have to do though is to register your component class as a service in the service configuration.

```PHP
<?php declare(strict_types=1);

use Shopware\Storefront\Resources\views\components\Button;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(Button::class)
        ->autoconfigure(true);
};

```

Now your component is all set up and ready to go. You can directly access it in the template under the given name and namespace.

**Note:** Be careful with adding too much business logic inside component classes. At Shopware we typically implement anonymous components that are pure frontend representations which get passed all necessary data via properties.


## Adding components via Shopware extension

New components can easily be added by Shopware apps and plugins.

### Anonymous Components
The anonymous components work right of the box and you don't have to do anything else than putting a template file under `<your-plugin>/src/Resources/views/components`. Both, apps and plugins can provide anonymous components in any distribution form of Shopware. Your components will automatically be prefixed with the bundle name of your app or plugin.

**Example structure:**

```
MyExtension/
  src/
    Resources/
      views/
        commponents/
          Button/
            Primary.html.twig

```

This will result in the following component being registered:

```Twig
<twig:MyExtension:Button:Primary />
```

For further information about anonymous components you can have a look at the [official documentation](https://symfony.com/bundles/ux-twig-component/current/index.html#anonymous-components).

### Components with PHP class
Components that make use of a PHP class can only be provided by plugins, not via apps. If you want to use a PHP class for a component in your plugin, you can simply create it next to your other component files, like described above. You can then define your component. Make sure to use the right namespace.

**Example:**

```
MyPlugin/
  src/
    Resources/
      config/
        services.xml
      views/
        components/
          Button/
            Primary.html.twig
            Primary.php

```


```PHP
<?php declare(strict_types=1);

namespace MyPlugin\Resources\views\components\Button;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent()]
class Primary
{
    public string $text = 'Click me!';

    // Add more public props and logic here.
}
```

The component class must be registered as a service in your plugin's service configuration with `autoconfigure`. This lets Symfony read the `#[AsTwigComponent]` attribute and wire up everything — including public property exposure — automatically. Without this registration the PHP class is unknown to the container and Twig silently falls back to an anonymous (template-only) component.

```PHP
// src/Resources/config/services.php
<?php declare(strict_types=1);

use MyPlugin\Resources\views\components\Button\Primary;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(Primary::class)
        ->autoconfigure(true);
};

```

Now you can use your component in the template.

```Twig
<twig:MyPlugin:Button:Primary />
```

To learn what kind of possibilities the PHP implementation of your component offers, you can just refer to the [official documentation](https://symfony.com/bundles/ux-twig-component/current/index.html).


## Best Practices

### Class naming

* Each component must have a unique root class that is used throughout the component markup.
* The root class uses the `sw-` prefix to be independent of existing/old components.
* Child elements must "follow" the root class naming.
* The BEM naming pattern is used.

✅ Do:
```html
<div class="sw-product-card card">
    <div class="sw-product-card__body card-body">
        <h2 class="sw-product-card__title">Card title</h2>
    </div>
    <div class="sw-product-card__footer card-footer">
    </div>
</div>
```

```css
.sw-product-card {
    /* Styling */
}

.sw-product-card__body {
    /* Styling */
}

.sw-product-card__title {
    /* Styling */
}
```

❌ Don't
```html
<div class="sw-product-card card">
    <div class="sw-card-inner card-body">
        <h2>Card title</h2>
    </div>
    <div class="sw-card-bottom card-footer">
    </div>
</div>
```

```css
.sw-product-card {
    .sw-card-inner {
        h2 {
            /* Styling */
        }
    }
}
```

### Component APIs and extensibility

A UX component can offer several APIs to allow extension. Extension means that the APIs of a component can be used to configure or modify a component during its usage.
The APIs of a component are "Props", "Blocks", "Attributes" and "Slots". Slots are an additional feature in Shopware to allow component extension using the CMS.

| API             | Description                                                                                                                              | Twig UX standard | Public API  |
|-----------------|------------------------------------------------------------------------------------------------------------------------------------------|------------------|-------------|
| Props           | Component properties for configuration that can be passed to a component during usage. (Similar to props in Vue.js or React)             | ✅               | ✅          |
| Twig blocks     | Used to overwrite HTML content programmatically when the component is used in a template. (Similar to slots in Vue.js or web components) | ✅               | ✅          |
| Twig attributes | Override or add HTML attributes during component usage without using Twig blocks.                                                        | ✅               | ✅          |
| Slots           | Used to declare an entry-point for other components that can be slotted via the API and the CMS.                                         | ❌               | ✅          |

## Props

* A prop must have a clear purpose and must only have one task.
* Props are written in camelCase.
* If possible, provide a fallback value for props in case a prop is not given.
* If a component cannot work without a specific prop, the prop can also be required.

```twig
{% props
    product,            {# Required prop #}
    mediaHeight = 240,  {# With fallback #}
    defaultRoute = '#', {# With fallback #}
%}

<twig:Sw:ProductCard :product="product" />
```

### Blocks

* A larger component should bring blocks for logical sections of the component to allow programmatic customization of the component.
* A block must always have a dedicated purpose. Avoid creating blocks speculatively without a defined use case.
* Modifying markup like class attributes or ids should be done with [Attributes and CVA](#Attributes-and-CVA) and not with the block system.
* It must never be necessary to use twig blocks in order to change simple attributes of a component.
* A component that is used like a native element (e.g. a button component) must bring a default content block `{% block content %}`. The content block works similar to a default slot in other component frameworks. It is needed to pass the content into the component without the need to use a named block.
  ```twig
  {# Component #}
  <button class="sw-button">
     {% block content %}{% endblock %}
  </button>

  {# Usage #}
  <twig:Sw:Button>Button text in content block</twig:Sw:Button>
  ```
* A component block must not be prefixed with the components name since it is automatically namespaced to the component.
* Not all components must have blocks. For example when there is no inner HTML element that would make sense to customize.

✅ Do:
```twig
{# Blocks are declared in dedicated areas to allow overriding of logical sections. #}
<div class="sw-product-card card">
    {% block media %}
        <img src="..." class="card-img-top" alt="...">
    {% endblock media %}

    <div class="sw-product-card__body card-body">
        {% block title %}
            <h2 class="sw-product-card__title card-title">{{ product.name }}</h5>
        {% endblock %}

        {% block price %}
            <p class="sw-product-card__price card-text">{{ product.price|currency }}</p>
        {% endblock %}
    </div>

    <div class="sw-product-card__footer card-footer">
        {% block actions %}
            {# Actions #}
        {% endblock %}
    </div>
</div>
```

❌ Don't
```twig
{# Blocks are declared around every element to have potential override possibities. #}
{% block product_card %}
    <div class="sw-product-card card">
        {% block product_card_inner %}

            {% block product_card_header %}
                <img src="..." class="card-img-top" alt="...">
            {% endblock %}

            {% block product_card_body %}
                <div class="sw-product-card__body card-body">
                    {% block product_card_body_inner %}
                        <h2 class="sw-product-card__title card-title">{{ product.name }}</h5>

                        <p class="sw-product-card__price card-text">{{ product.price|currency }}</p>
                    {% endblock %}
                </div>
            {% endblock %}

            {% block product_card_footer %}
                <div class="sw-product-card__footer card-footer">
                    {% block product_card_footer_inner %}
                        {# Actions #}
                    {% endblock %}
                </div>
            {% endblock %}

        {% endblock %}
    </div>
{% endblock %}
```

### Slots

* A larger component should bring slots as entry-points for the API and the CMS.
* The `<twig:Slot></twig:Slot>` component is used to declare slots for extending the component.
* A prop `slots` of type array is used to pass content into the slots.
* The slot component renders the content of the provided slots.
* A slot must always bring a unique name inside the current component.
* A slot can have a default content that is nested inside the `<twig:Slot></twig:Slot>`.
* A slot for API extension can be used in addition to a `{% block %}` that is used for programmatic extension.
* A slot must not use the self-closing tag and must be always verbose: `<twig:Slot></twig:Slot>`.

✅ Do:
```twig
<twig:Slot name="header"></twig:Slot>
```

❌ Don't
```twig
<twig:Slot name="header" />
```

#### Declaring slots:
```twig
{% props
    product,
    slots = [] {# Prop that contains the content of the slots. #}
%}

<div class="sw-product-card card">

    <div class="sw-product-card__header">
        {# Entry-point for before the core contents of the header. #}
        <twig:Slot name="header-top"></twig:Slot>

        <img src="..." class="w-product-card__media card-img-top" alt="...">

        {# Entry-point for after the core contents of the header. #}
        <twig:Slot name="header-top"></twig:Slot>
    </div>

    {# ... #}
</div>
```

#### Slots with default content:
```twig
{% props
    product,
    slots = [] {# Prop that contains the content of the slots. #}
%}

<div class="sw-product-card card">
    <div class="sw-product-card__header">
        {# Entry-point for before the core contents of the header. #}
        <twig:Slot name="header-top"></twig:Slot>

        {# Entry-point for media with default content. #}
        <tiwg:Slot name="media">
            <img src="..." class="w-product-card__media card-img-top" alt="...">
        </twig:Slot>

        {# Entry-point for after the core contents of the header. #}
        <twig:Slot name="header-top"></twig:Slot>
    </div>

    {# ... #}
</div>
```

#### Slots in combination with blocks:
```twig
{% props
    product,
    slots = [] {# Prop that contains the content of the slots. #}
%}

<div class="sw-product-card card">
    <div class="sw-product-card__header">

        {# Block to overwrite the content programmatically when using the component in a twig template. #}
        {% block media %}

            {# Entry-point for API and CMS. #}
            <tiwg:Slot name="media">
                <img src="..." class="w-product-card__media card-img-top" alt="...">
            </twig:Slot>
        {% endblock %}

    </div>

    {# ... #}
</div>
```

### Attributes and CVA (Class Variance Authority)

* A component should make attributes extendable using the attributes feature of Symfony UX.
* Attributes must not be hardcoded in the HTML elements.
* Use nested attributes for child-elements.
* A component should also use CVA to make the CSS-classes configurable.
* The CVA must bring the `base` variant by default.
* A prop should be available to allow extending the CVA variants.
* A prop should be available to allow changing of the base classes of the component.

✅ Do:
```twig
{% props
    defaultBaseClasses = 'sw-product-card card',
    defaultVariants = {},
%}
{% set rootVariants = defaultVariants|merge({
    base: defaultBaseClasses,
}) %}
{% set rootCVA = cva(rootVariants) %}
<div {{ attributes.defaults({ role: 'article' }) }} class="{{ rootCVA.apply({}, attributes.render('class')) }}">
    ...
</div>
```

❌ Don't
```twig
<div class="sw-product-card card" role="article">
    ...
</div>
```

### Data independence and global state access

* A component should be as independent as possible.
* A component must not rely on its parent component in order to function correctly.
* A component must not rely on global variables or state internally in order to function correctly.
* If a global setting is needed e.g. `config('core.listing.allowBuyInListing')` it should be able to be passed as a prop from outside.
* A component can use Symfony translation internally.

```twig
{# ProductCard.html.twig #}
{% props
    name = 'Example product',
    allowsBuyAction = config('core.listing.allowBuyInListing')
%}
{# Usage: #}
<twig:Sw:ProductCard
    name="{{ product.name }}"
    allowsBuyAction="false"
/>
```

### Nested components

* It is allowed to create nested components that are part of a larger component.
* It is recommended that components that are part of one "Domain" are grouped in one namespace directory.
* It should be avoided to create deep nesting structures and complex compositions of different components. Components should be as simple as possible.
* If a component should be used in other areas as well it should be an independent component instead.

```
components/Product
    Card.html.twig
    Actions.html.twig
    PriceDisplay.html.twig
```

```twig
<twig:Sw:Sw:Product:Card>
    <twig:Sw:Product:Card:Actions />
</twig:Sw:Product:Card>
```

### CSS

* The CSS of a component lives in the same directory as the component template.
* It is recommended to use native CSS features over SCSS. This uses more web standards over proprietary SCSS library features and allows more runtime customization in plain CSS.
* It is recommended that components bring their own CSS custom properties (CSS variables) to allow easy customization without overwriting properties on selectors directly.
* Custom properties must bring an `sw-` prefix to avoid conflicts with Bootstrap `bs-` variables.
* When a Twig UX component uses a Bootstrap component, use the CSS variables from Bootstrap over custom styling.
* The BEM naming pattern is used for CSS.
* It should be avoided to have deep nesting in CSS. Always prefer a unique class name over nesting.

```
components/Product
    Card.html.twig
    Card.scss
```

If you use the index naming of anonymous components, you have to name your SCSS file in the same way.

```
components/Alert
    index.html.twig
    index.scss
```

✅ Do:
```css
.sw-product-card {
    /* Uses Bootstrap CSS variables for customization */
    --bs-card-spacer-y: 1rem;
    --bs-card-spacer-x: 1rem;
    --bs-card-border-color: var(--bs-border-color);

    /* Declares own variables to allow customization */
    --sw-product-card-media-height: 200px;
}

/* Avoids nesting by using BEM naming structure */
.sw-product-card__media {
    height: var(--sw-product-card-media-height);
}

/* Avoids nesting by using BEM naming structure */
.product-card__price {
    font-variant-numeric: tabular-nums;
}
```

❌ Don't
```scss
.sw-product-card {
    /* Overriding border manually instead of using the Bootstrap CSS variables --bs-card-spacer. */
    /* Uses a SCSS variable when a CSS variable can be used instead. */
    border: 1px solid $border-color;

    .sw-product-card__media {
        /* Hardcodes values that might be worth customizing */
        height: 200px;
    }

    .card-body {
        /* Uses custom selector for hard styling overwrite to customize the padding */
        /* instead of using the Bootstrap CSS variables --bs-card-spacer. */
        padding: 1rem;

        /* Uses deep nesting that is not needed with proper naming */
        .product-card__price {
            font-variant-numeric: tabular-nums;
        }
    }
}
```

## JavaScript

You can add a corresponding JS/TS file for your component. Component JavaScript is built with Vite into ES modules and loaded via import map when the component is used on a page. You can use plain JS/TS or the Shopware component system to add encapsulated frontend logic.

For more information you can have a look at the [README.md](../../app/storefront/src/component-system/README.md).

## Vite build essentials

### Build and publish from project root

Use the Composer commands from the project root:

```bash
# Full Storefront JS build (includes component build + publish)
composer build:js:storefront
```

### Dev server from project root

For local development with hot reload and live component imports, start the Storefront Vite dev server:

```bash
composer storefront:dev-server
```

In dev mode, Shopware loads component JS/CSS directly from the running Vite server.
Normal theme files are also compiled and served with Vite in dev-server mode, so you can work with all together.
When the dev server stops, Shopware falls back to published production assets.

### What the pipeline does

1. Vite builds component JS/TS/SCSS/CSS from `Resources/views/components/` into `Resources/public/storefront/components/`.
2. `assets:install` publishes those files into `public/bundles/<bundle>/storefront/components/`.
3. Theme compile reads bundle-local Vite manifests and injects component imports/styles at runtime.

### Custom Vite config for apps/plugins

A bundle can provide `Resources/app/storefront/vite.components.config.mts`. Use the shared Shopware factory and only override what you need:

```ts
import path from 'node:path';
import { createComponentBuildConfig } from '../../../../../../src/Storefront/Resources/app/storefront/build/vite/component-config-factory';

export default async () => {
    const storefrontAppDir = import.meta.dirname;

    return createComponentBuildConfig({
        componentRoot: path.resolve(storefrontAppDir, '../../views/components'),
        outDir: path.resolve(storefrontAppDir, '../../public/storefront/components'),
        namespace: 'MyExtension',
        storefrontAppDir,
        sourcemap: true,
    });
};
```

### Build security defaults

When bundle-local dependencies are installed during `build:components`, lifecycle scripts are blocked by default:

- With lockfile: `npm ci --include=dev --ignore-scripts`
- Without lockfile: `npm install --prefer-offline --include=dev --ignore-scripts`

This reduces supply-chain risk for CI and untrusted extension sources.
Only enable install scripts in trusted environments by setting:

```bash
ALLOW_EXTENSION_INSTALL_SCRIPTS=1
```
