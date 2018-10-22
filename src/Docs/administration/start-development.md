# Start administration development

This guide will teach you how to start developing with the shopware administration.

All commands which are necessary for administration development can be accessed from the root directory of your shopware instance. The administration commands are prefixed with `administration`:

```
./psh.phar administration:{COMMAND}
```

<a href="https://github.com/shopwareLabs/psh">Find out more about about PSH</a>.


## 1. Install dependencies

To get going you first need to install development dependencies with the `init` command:

```
./psh.phar administration:init
```

This will install all necessary dependencies for your local development environment using <a href="https://www.npmjs.com/">NPM</a>.

## 2. Local development Server

For local development you can start a development server from your terminal. This will also enable a file watcher which will update the page in your browser when you make any changes to your files. Even when the the browser is refreshing the page the current state of the application remains the same. You can stay at the same place where you're working at. The watcher also offers automatic linting using ESLint and will show an overlay with helpful error messages.

Start the development server:

```
./psh.phar administration:watch
```

This will start the development server with `localhost` and the default port `8080`:

```
http://localhost:8080
```
If you need port `8080` for something else like e.g. elastic search, you can also change the port with the additional `DEVPORT parameter:
``
```
./psh.phar administration:watch --DEVPORT=9000
```

The `./psh.phar administration:watch` command opens a new window of your default browser with the correct URL of the development server.

## 3. Using the Vue.js Developer Tools

The Vue.js framework offers an extension for the developer console of your browser. With this extension you have a reference to the original component structure and can inspect each component to get live information about it's state, events and several other information. This can be a really helpful tool during development.

[SCREENSHOT_DEVTOOLS]

- <a href="https://chrome.google.com/webstore/detail/vuejs-devtools/nhdogjmejiglipccpnnnanhbledajbpd">Vue.js Devtools for Google Chrome</a>
- <a href="https://addons.mozilla.org/en-US/firefox/addon/vue-js-devtools/">Vue.js Devtools for Firefox</a>

The Google Chrome Version should also work in other webkit based browsers like Chromium, Opera or Vivaldi.

# Create your first plugin

Let's create an example plugin called "SwagAdministrationExample".

## 1. Create the plugin bootstrap file

All plugins are located inside the `{YOUR_SW_ROOT}/custom/plugins` directory.

In order to make your plugin functional you need at least a plugin bootstrap file inside the root directory of your plugin. _Please beware that the file name has to be equal to the name of the plugin._

```
.
└── plugins
    └── SwagAdministrationExample
        └── SwagAdministrationExample.php
```

The bare minimum of a plugin bootstrap looks like this:

```
<?php declare(strict_types=1);

namespace SwagAdministrationExample;

use Shopware\Core\Framework\Plugin;

class SwagAdministrationExample extends Plugin {

}
```

This is already a valid shopware plugin. You can however add additional functionality like for example custom install or update methods. <a href="#">Learn about Plugins</a>. Only the bootstrap class is required to start making changes in the administration.

## 2. Install the plugin

Before you make further changes to the administration itself you should install the plugin first in order to see all upcoming changes.

You can manage plugins with the `plugin` command of the <a href="#">Shopware CLI</a>.

First of all you can list all plugins which are currently available:

```
bin/console plugin:list
```

This will promt an overview table with the available plugins in you terminal:

```
------------------- ------------------- --------- -------- -------- -----------
 Plugin              Label               Version   Author   Active   Installed
------------------- ------------------- --------- -------- -------- -----------
 SwagHelloWorld      SwagHelloWorld      1.0.0              No       No
------------------- ------------------- --------- -------- -------- -----------

1 plugins, 0 installed, 0 active
```

In case your plugin does not show up you can refresh the plugin list with the `update` command:

```
bin/console plugin:update
```

Finally you can install your plugin. The `--activate` argument also enables the new plugin so you can see the changes right away:

```
bin/console plugin:install SwagHelloWorld --activate
```

When the plugin was successfully activated, please restart the development server. Webpack will then keep track of the new plugin and add the administration files to the file watcher process.

## 3. Making changes

The administration files of your plugin are located in the `Resources/views/administration` directory.

The entry point for your administration changes is the `main.js` file. In this file you can import all your components and modules later on.

```
SwagAdministrationExample
├── Resources
│   └── views
│       └── administration
│           └── main.js
└── SwagAdministrationExample.php
```

To make actual changes you have two main possibilities. Writing entirely new components or using the multi inheritance system from shopware. The inheritance system allows you to extend or override existing functionality with your own code. You don't need to copy large chunks of the source code in order to make your desired change.

Shopware is using a custom build of Twig.js to make this possible for the administration templates. 

You can use all Vue components of the shopware core as an entry point in order to make changes. Let's take a look at the component template of `sw-dashboard`:

```
{% block sw_dashboard_index %}
    <sw-page class="sw-dashboard-index" :showSmartBar="false">
        {% block sw_dashboard_index_content %}
            <sw-card-view slot="content" class="sw-dashboard-index__content">
                {% block sw_dashboard_index_content_view %}{% endblock %}
            </sw-card-view>
        {% endblock %}
    </sw-page>
{% endblock %}
```
As you can see the most important parts of a core template are wrapped inside Twig blocks. This gives you the possibility to override or append any of those blocks in your own plugin. Just like you are maybe already familiar with from frameworks like Symfony.

Please beware that the custom build of Twig.js only contains the block feature to give you the power of making template driven changes without overriding the whole component template. It does however not include features like `{% for %}` or `{% set %}`. Stuff like variables, for loops or modifiers are completely handled by Vue.js. Just think about it this way: You are looking at Vue.js Code with additional Twig blocks.

### Overriding a component

Now you can add a new component to your plugin. The component needs at least an `index.js` file and a template:

```
SwagAdministrationExample
├── Resources
│   └── views
│       └── administration
│           ├── main.js
│           └── src
│               └── extension
│                   └── sw-dashboard-index
│                       ├── index.js
│                       └── sw-dashboard-index.html.twig
└── SwagAdministrationExample.php
```
All components are registered globally by the component factory and have to use unique names. So it does not really matter where exactly your components are located inside the `administration/src` directory your plugin. The `extension` directory is just a nice convention to keep things organized and separate your new components from components you are extending or overriding.

The new component can override an existing component by using the `Component.override()` feature. <a href="#">Learn more about components</a>.

```
import { Component } from 'src/core/shopware';
import template from './sw-dashboard-index.html.twig';

Component.override('sw-dashboard-index', {
    template,

    created() {
        console.log('Dashboard override loaded.');
    }
});
```
Inside the template you can call the `sw_dashboard_index_content_view` block from the `sw-dashboard-index` component and implement your own content:

```
{% block sw_dashboard_index_content_view %}
    <!-- Your content goes here -->
    <div class="swag-administration-example-dashboard">
        <h1>Administration Example</h1>
    </div>
{% endblock %}
```

### Using the component

Now you can import the component inside the `main.js` file:

```
import 'src/extension/sw-dashboard-index';
```

After the import you should be able to the your changes in the dashboard:

[SCREENSHOT_DASHBOARD]

## 4. Prepare plugin CSS and JavaScript for production

In order to build the CSS and JavaScript for production environments you need to append your files in the index template for the production build:

```
{% sw_extends 'administration/index.html.twig' %}

{% block administration_stylesheets %}
    {{ parent() }}
    <link rel="stylesheet" href="{{ asset('bundles/swagadministrationexample/static/css/SwagHelloWorld.css') }}">
{% endblock %}

{% block administration_scripts %}
    {{ parent() }}
    <script type="text/javascript" src="{{ asset('bundles/swagadministrationexample/static/js/SwagHelloWorld.js') }}"></script>
{% endblock %}
```

### Build administration files

You can create the production files by using the `build` command:

```
./psh.phar administration:build
```

## 5. Working with assets

You have the ability to use assets (e.g. images) inside your plugin.

### Asset location

All assets are located inside the `public/static` directory:

```
SwagAdministrationExample
└── Resources
    └── public
        └── static // Assets are located here
            └── img
                └── shopware.jpg
```

### Using assets

You can use relative paths based on your plugin directory inside LESS and CSS files:

```
.selector {
    background-image: url('../img/shopware.jpg');
}
```

Inside the templates files you have to use absolute paths.
The path contains your plugin name (all lowercase and without separators), followed by `static`
and all further direcories inside of `static`:

The asset path for an image inside the "SwagAdministrationExample" plugin looks like this:

```
<img :src="'/swagadministrationexample/static/img/shopware.jpg' | asset" alt="Shopware logo" />
```

Don't forget to use the `asset` filter to make sure the URL gets resolved correctly.
