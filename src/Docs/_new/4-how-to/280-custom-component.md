[titleEn]: <>(Creating a custom component)
[metaDescriptionEn]: <>(Since the Shopware platform's Administration is using VueJS as its framework, it also supports creating custom components. This How-to will teach you real quick how to register your own custom component with your plugin.)

## Overview

Since the Shopware platform's Administration is using [VueJS](https://vuejs.org/) as its framework, it also supports creating
custom components. This How-to will teach you real quick how to register your own custom component with your plugin.

In this example, you will create a component, that will print a 'Hello world!' everywhere it's being used.

## Setup

This HowTo **does not** explain how to create a new plugin for the Shopware platform.
Head over to our [Plugin quick start guide](./../2-internals/4-plugins/010-plugin-quick-start.md) to
learn creating a plugin at first.

## Injecting into the administration

The main entry point to extend the administration via plugin is the `main.js` file.
It has to be placed into a `<plugin root>/src/Resources/admininistration` directory in order to be found by the Shopware
platform.
*Note: This path can be changed by overriding the [getAdministrationEntryPath](./../2-internals/4-plugins/020-plugin-base-class.md#getAdministrationEntryPath) method of your plugin's base class.*

## Creating a custom component

Usually there's one question you have to ask yourself first:
Will your new component be used as a `page` for your plugin's custom route, or is this going to be a component to be used
by several other components, such as an element that prints 'Hello world' everywhere it's used?

### Path to the component

In order to properly structure your plugin's code and to be similar to the core structure, you have to answer this question first.
If it's going to be used as page for a module, it should be placed here:
`<plugin-root>/src/Resources/administration/module/<your module's name>/page/<your component name>`

Otherwise, if it's going to be a general component to be used by other components, the following will be the proper path.
For this example, this scenario is used.
`<plugin-root>/src/Resources/administration/app/component/<name of your plugin>/<name of your component>`

Those are **not** a hard requirement, but rather a recommendation. This way, third party developers having a glance at your code will
get used to it real quick, because you sticked to the Shopware platform's core conventions.

Since the latter example is being used, this is the path being created in the plugin now:
`<plugin-root>/src/Resources/administration/app/component/custom-component/hello-world`

### Main.js

In the directory mentioned above, create a new file `index.js`, which is going to be loaded automatically.
Now import your custom component using your plugin's `main.js` file:

```js
import './app/component/custom-component/hello-world';
```


### Index.js

Head back to the `index.js` file, this one will be the most important for your component.

First of all, you have to register your component using the `ComponentFactory`, which is available throughout our third party wrapper.

```js
import { Component } from 'src/core/shopware';
```

This `Component` provides a method `register`, which expects a name and a configuration for your component.

```js
import { Component } from 'src/core/shopware';

Component.register('hello-world', {
    // Configuration here
});
```

A component's template is being defined by using the `template` property. For this short example, the template will be defined inline.
An example for a bigger template will also be provided later on this page.

```js
import { Component } from 'src/core/shopware';

Component.register('hello-world', {
    template: '<h2>Hello world!</h2>'
});
```

That's it. You can now use your component like this `<hello-world></hello-world>` in any other template in the Administration.

### Long template example

It's quite uncommon to have such a small template example and you don't want to define huge templates inside a javascript file.
For this case, just create a new template file in your component's directory, which should be named after your component.
For this example `hello-world.html.twig` is used.

Now simply import this file in your component's JS file and use the variable for your property.
```js
import { Component } from 'src/core/shopware';
import template from 'hello-world.html.twig';

Component.register('hello-world', {
    template: template
});
```

In the core code, you will find another syntax for the same result though:
```js
import { Component } from 'src/core/shopware';
import template from 'hello-world.html.twig';

Component.register('hello-world', {
    template
});
```

This is a [shorthand](https://alligator.io/js/object-property-shorthand-es6/), which can only be used if the variable is named exactly like the property.

## Loading the JS files

As mentioned above, the Shopware platform looks for a `main.js` file in your plugin.
Its contents get minified into a new file named after your plugin and will be moved to the `public` directory
of the Shopware platform root directory.
Given this plugin is named "CustomComponent", the minified javascript code for this example would be
located under `<plugin root>/src/Resources/public/static/js/CustomComponent.js`, once you run the command `./psh.phar administration:build` in your shopware root directory.
*Note: Your plugin has to be activated for this to work.*
Make sure to also include that file when publishing your plugin!
A copy of this file will then be put into the directory `<shopware root>/public/bundles/customcomponent/static/js/CustomComponent.js`.

The latter javascript file has to be injected into the template by your plugin as well for production environments.
In order to do this, create a new file called `index.html.twig` here: `<plugin root>/src/Resources/views/administration/`

```twig
{% sw_extends 'administration/index.html.twig' %}

{% block administration_scripts %}
    {{ parent() }}
    <script type="text/javascript" src="{{ asset('bundles/customcomponent/static/js/CustomComponent.js') }}"></script>
{% endblock %}
```

Your minified javascript file will now be loaded in production environments.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-custom-component).