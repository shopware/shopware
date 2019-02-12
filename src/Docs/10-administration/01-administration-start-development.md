This guide will teach you how to start developing with the shopware administration.

All commands which are necessary for administration development can be accessed from the root directory of your shopware instance.
The administration commands are prefixed with `administration`:

```
./psh.phar administration:{COMMAND}
```

[Find out more about about PSH](https://github.com/shopwareLabs/psh).


## Install dependencies

To get going you first need to install the development dependencies with the `init` command:

```
./psh.phar administration:init
```

This will install all necessary dependencies for your local environment using [NPM](https://www.npmjs.com/).

## Local development Server

For local development you can start a server from your terminal.
This will also enable a file watcher which will update the page in your browser when you make any changes to your files.
Even when the the browser is refreshing the page the current state of the application remains the same.
You can stay at the same place where you're working at.
The watcher also offers automatic linting using ESLint and will show an overlay with helpful error messages.

Start the development server:

```
./psh.phar administration:watch
```

This will start the development server with `localhost` and the default port `8080`:

```
http://localhost:8080
```
If you need port `8080` for something else like e.g. elastic search, you can also change the port with the additional `DEVPORT` parameter:

```
./psh.phar administration:watch --DEVPORT=9000
```

The `./psh.phar administration:watch` command opens a new window of your default browser with the URL of the development server.

The shopware administration can be reached at `/admin`.
If you have followed the other parts of the guide, an administrator account will have automatically been created: 

Username | Password
-------- | --------
admin    | shopware

## Using the Vue.js developer tools

The [Vue.js](https://vuejs.org/) framework offers an extension for the developer console of your browser.
With this extension you have a reference to the original component structure and can inspect each component to get live information about it's state, events and several other information.
This can be a really helpful tool during development.

![Vue.js developer tools](https://sbp-testingmachine.s3.eu-west-1.amazonaws.com/1541782342/vuejs-devtools.jpg)

- [Vue.js Devtools for Google Chrome](https://chrome.google.com/webstore/detail/vuejs-devtools/nhdogjmejiglipccpnnnanhbledajbpd)
- [Vue.js Devtools for Firefox](https://addons.mozilla.org/en-US/firefox/addon/vue-js-devtools/)

The Google Chrome Version should also work in other webkit based browsers like Chromium, Opera or Vivaldi.

# Create your first plugin

Let's create an example plugin called "SwagAdministrationExample".

[Read this article](../60-plugin-system/01-getting-started.md) to learn the basics of the plugin system. 

## Install the plugin

Before you make further changes to the administration itself you should install the plugin first in order to see all upcoming changes.

```
bin/console plugin:install SwagAdministrationExample --activate
```

When the plugin was successfully activated, please restart the development server.
Webpack will then keep track of the new plugin and add the administration files to the file watcher process.

## Making changes

The administration files of your plugin are located in the `Resources/views/administration` directory.

The entry point for your administration is the `main.js` file.
In this file you can import all your components and modules later on.

```
SwagAdministrationExample
├── Resources
│   └── views
│       └── administration
│           └── main.js
└── SwagAdministrationExample.php
```
*file structure*

To make actual changes you have two main possibilities.
Writing entirely new components or using the multi inheritance system from shopware.
The inheritance system allows you to extend or override existing functionality with your own code.
You don't need to copy large chunks of code in order to make your desired change.

Shopware is using a custom build of [Twig.js](https://github.com/twigjs/twig.js) to make this possible for the administration templates. 

You can use all Vue components of the shopware core as an entry point in order to make changes.
Let's take a look at the component template of `sw-dashboard`:

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
As you can see the most important parts of a core template are wrapped inside Twig blocks.
This gives you the possibility to override or append any of those blocks in your own plugin.
Just like you are maybe already familiar with from frameworks like Symfony.

Please note that the custom build of Twig.js only contains the block feature to give you the power of making template driven changes without overriding the whole component template.
It does however not include features like `{% for %}` or `{% set %}`.
Stuff like variables, for loops or modifiers are completely handled by Vue.js.
Just think about it this way: You are looking at Vue.js Code with additional Twig blocks.

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
*file structure*

All components are registered globally by the component factory and have to use unique names.
So it does not really matter where exactly your components are located inside the `administration/src` directory your plugin.
The `extension` directory is just a nice convention to keep things organized and separate your new components from components you are extending or overriding.

The new component can override an existing component by using the `Component.override()` feature. [Learn more about components](./20-create-a-component.md).

```
// sw-dashboard-index/index.js

import { Component } from 'src/core/shopware';
import template from './sw-dashboard-index.html.twig';

Component.override('sw-dashboard-index', {
    template,

    created() {
        console.log('Dashboard override loaded.');
    }
});
```
*overriding a component*

Inside the template you can call the `sw_dashboard_index_content_view` block from the `sw-dashboard-index` component and implement your own content:

```
// sw-dashboard-index/sw-dashboard-index.html.twig

{% block sw_dashboard_index_content_view %}
    <!-- Your content goes here -->
    <div class="swag-administration-example-dashboard">
        <h1>Administration Example</h1>
    </div>
{% endblock %}
```
*adding a template*

### Using the component

Now you can import the component inside the `main.js` file:

```
// administration/main.js

import './src/extension/sw-dashboard-index';
```
*importing the new component*

After the import you should be able to the your changes in the dashboard:

![Screenshot dashboard](https://sbp-testingmachine.s3.eu-west-1.amazonaws.com/1541782334/administration-example-plugin-dashboard.jpg)

## Prepare plugin CSS and JavaScript for production

In order to build the CSS and JavaScript for production environments you need to append your files in the index template for the production build:

```
// administration/index.html.twig

{% sw_extends 'administration/index.html.twig' %}

{% block administration_stylesheets %}
    {{ parent() }}
    <link rel="stylesheet" href="{{ asset('bundles/swagadministrationexample/static/css/SwagAdministrationExample.css') }}">
{% endblock %}

{% block administration_scripts %}
    {{ parent() }}
    <script type="text/javascript" src="{{ asset('bundles/swagadministrationexample/static/js/SwagAdministrationExample.js') }}"></script>
{% endblock %}
```
*include JavaScript and CSS files for production build*

### Build administration files

You can create the production files by using the `build` command:

```
./psh.phar administration:build
```

## Working with assets

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
*file structure for assets*

### Using assets

You can use relative paths based on your plugin directory inside LESS and CSS files:

```
.selector {
    background-image: url('../img/shopware.jpg');
}
```

Inside the templates files you have to use absolute paths.
The path contains your plugin name (all lowercase and without separators), followed by `static`
and all further directories inside of `static`:

The asset path for an image inside the "SwagAdministrationExample" plugin looks like this:

```
<img :src="'/swagadministrationexample/static/img/shopware.jpg' | asset" alt="Shopware logo" />
```

Don't forget to use the `asset` filter to make sure the URL gets resolved correctly.
