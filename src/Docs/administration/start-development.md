# Start development

This guide will teach you how to start developing with the shopware administration.

All commands which are necessary for administration development can be accessed from the root directory of your shopware instance. The administration commands are prefixed with `administration`:

```
./psh.phar administration:{COMMAND}
```

<a href="#">Find out more about about PSH</a>.


## 2. Install dependencies

To get going you first need to install development dependencies with the `init` command:

```
./psh.phar administration:init
```

This will install all necessary dependencies for your local development environment using <a href="https://www.npmjs.com/">NPM</a>.

## 3. Start Local development Server

For local development you can start a development server from your CLI. This will also enable a file watcher which will update the page in your browser when you make any changes to your files. So you automatically stay at the same place where you're working at. The watcher also offers automatic linting using ESLint and will show an overlay with helpful error messages.

Start the development server:

```
./psh.phar administration:watch
```

This will start the development server which is available with this URL:

```
http://localhost:8080
```

Usually the `./psh.phar administration:watch` command opens a new window of your default browser with the correct URL of the development server.

### Vue developer console

The Vue.js framework offers an extension for the developer console of your browser. Here you have a reference to the original component structure and can inspect each component to get live information about its state, events and several other information. This can be a really helpful tool during development.

[SCREENSHOT_DEVTOOLS]

<ul>
	<li>
		<a href="https://chrome.google.com/webstore/detail/vuejs-devtools/nhdogjmejiglipccpnnnanhbledajbpd">Vue.js Devtools for Google Chrome</a>
	</li>
	<li>
		<a href="https://addons.mozilla.org/en-US/firefox/addon/vue-js-devtools/">Vue.js Devtools for Firefox</a>
	</li>
</ul>

## 4. Build administration files

```
./psh.phar administration:build
```

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

## 2. Create administration files

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

To make actual changes you have two main possibilities. Writing entirely new components or using the multi inheritance system from shopware. The inheritance system allows you to extend or override existing functionality with your own implementation. You don't need to copy large chunks of the source code in order to make your desired change.

Shopware is using a custom build of Twig.js to make this possible for the administration templates. 

## 3. Install the plugin

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

In case your plugin doesn't show up you can refresh the plugin list with the `update` command:

```
bin/console plugin:update
```

Finally you can install your plugin. The `--activate` argument also enables the new plugin so you can see the changes right away:

```
bin/console plugin:install SwagHelloWorld --activate
```

## 4. Prepare plugin CSS and JavaScript for production

Index template for production build:

```
{% sw_extends 'administration/index.html.twig' %}

{% block administration_stylesheets %}
    {{ parent() }}
    <link rel="stylesheet" href="{{ asset('bundles/swaghelloworld/static/css/SwagHelloWorld.css') }}">
{% endblock %}

{% block administration_scripts %}
    {{ parent() }}
    <script type="text/javascript" src="{{ asset('bundles/swaghelloworld/static/js/SwagHelloWorld.js') }}"></script>
{% endblock %}
```

---

The administration is using Twig.js to provide the block system.

All core administration components have several twig blocks in order to make it easy to extend the templates.

```
{% block sw_alert %}
    <div class="sw-alert"></div>
{% endblock %}
```

You have the ability to extend or override blocks when you override a component.

--> Find out more in components: Override component <--
