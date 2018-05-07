# Guide: Administration module from scratch

The Shopware administration is a completely new project based on the [Vue.js ecosystem](https://vuejs.org) and [ECMAScript 6](http://es6-features.org/). The system is based on a so called thin layer architecture which abstracts the different application parts. The approach provides us with the ability to create independent components which can be easily nested together to create rich interfaces.

This guide assumes you're having a basic understanding of [Vue.js' component system](https://vuejs.org) and how to write a component.

## Table of Contents
  * [Write your first component](#write-your-first-component)
    + [Templating](#templating)
      - [Adding twig blocks to your component](#adding-twig-blocks-to-your-component)
      - [Why are you using Twig instead of plain templates?](#why-are-you-using-twig-instead-of-plain-templates-)
    + [Styling the component](#styling-the-component)
      - [Naming scheme](#naming-scheme)
      - [Scoping variables for your component](#scoping-variables-for-your-component)
      - [Importing your stylesheet](#importing-your-stylesheet)
    + [Adding basic functionalities](#adding-basic-functionalities)
      - [Extending the functionality even further](#extending-the-functionality-even-further)
    + [Localization of components](#localization-of-components)
  * [Using the component system](#using-the-component-system)
    + [Default component structure](#default-component-structure)
    + [Extending and overriding](#extending-and-overriding)
      - [Calling a method from an overridden / extended component](#calling-a-method-from-an-overridden---extended-component)
  * [Adding data to your component / module](#adding-data-to-your-component---module)
    + [State modules](#state-modules)
    + [Dependency management](#dependency-management)
      - [Mixins](#mixins)
    + [Creating your own mixin and state module](#creating-your-own-mixin-and-state-module)
      - [Fetching data from the API and API Services](#fetching-data-from-the-api-and-api-services)
  * [Register a module in the administration](#register-a-module-in-the-administration)
    + [Routing and routes definition](#routing-and-routes-definition)
      - [Aliases for routes](#aliases-for-routes)
    + [Main Menu Navigation entry](#main-menu-navigation-entry)
    + [Pages](#pages)
  * [The third-party developer interface](#the-third-party-developer-interface)
  * [Webpack, tooling and packing your module](#webpack--tooling-and-packing-your-module)
    + [Tooling](#tooling)

## Write your first component
Components are the fundamental part of the Shopware administration. Literally everything is a component. The thin layer architecture provides the interfaces to register new components or to extend and override existing components.

```js
// Import the shopware object, more later on
import { Component } from 'src/core/shopware';

// Register your component
Component.register('sw-hello-world', {
   template: '<div class="sw-component-example">Hello world!</div>'
});
```

*Registering your first component*

In the code example above, we are registering a new component in the application. In order to do so you have to import the `Component` object from the third-party interface. The interface takes care of delivering the component to the application and thereby registering it as a Vue.js component. This provides and injects a service provider and bootstraps it. 

### Templating

The template is one of the two main parts a component comprises of. Vue.js is a way to enhance a template with a bunch of useful features like two-way data binding, event binding within the template and conditional rendering.

We are using Twig template files for the administration, as Vue.js requires **exactly one** component "root" element. As a convention we are utilizing a `<div>` element carrying the component name as its class name.

```twig
<div class="sw-hello-world">
   {# ... #}
</div>
```

*Basic component template*

Save the file and name it after your component’s caption, e.g. `sw-hello-world.html.twig`.

```diff
// Import the shopware object, more later on
import { Component } from 'src/core/shopware';
+ import template from './sw-hello-world.html.twig'

// Register your component
Component.register('sw-hello-world', {
-   template: '<div class="sw-component-example">Hello world!</div>'
+   template
});
```

*Adding a template to a component*

From now on we will be importing the template from the newly created twig file instead of keeping the template as a string directly inside the component. Code separation is important, especially when you plan on creating complex components.

#### Adding twig blocks to your component

Components should always contain Twig blocks in order to allow the creation of extensions and overrides for them.

- The root block wraps the component and has the component name: `{% block sw_component %}`
- All block names of a component have the component name as a prefix.
- The `<slot>` element has an inner block: `{% block sw_component_slot_default %}`
- If there are multiple slots, they should be named after the slot name: `{% block sw_component_slot_message %}`

```twig
{% block sw_alert %}
    <div class="sw-alert" :class="alertClasses">
        {% block sw_alert_inner %}
            {% block sw_alert_icon %}
                <sw-icon :name="alertIcon" decorative />
            {% endblock %}

            {% block sw_alert_title %}
                <div v-if="title" class="sw-alert__title">{{ title }}</div>
            {% endblock %}

            {% block sw_alert_message %}
                <div class="sw-alert__message">
                    <slot>{% block sw_alert_slot_default %}{% endblock %}</slot>
                </div>
            {% endblock %}
        {% endblock %}
    </div>
{% endblock %}
```

*Examplary depiction of Component Twig blocks*

#### Why are you using Twig instead of plain templates?
You are probably wondering why the heck we are using Twig template files? The answer is simple: It gives us the ability to override and extend Twig blocks. This way it is super simple and very convenient to modify any part of the template. An added benefit is that you don't have to copy and paste large chunks of code you don't want to touch anyways. Furthermore, the technology is well-known and has already been part of Shopware's storefront. Lastly overriding templates works consistently throughout the application.

### Styling the component

We are using LESS for styling the component This provides you with options such as variables, nesting of selectors, mixins, inline media queries and much more. If you want to learn more about LESS and it's functionalities, you will find an [in-depth guide](http://lesscss.org/features/) on the [project homepage](http://lesscss.org/).

In your component's folder create a new LESS-file named after the component name , e.g. `sw-hello-world.less`.

Import our global variables to the newly created file in order to achieve a consistent look for your component:

```less
@import "~less/variables";

.sw-hello-world {
    // ...
}
```

#### Naming scheme

Because the Shopware administration is a component based application with rescuable components, the CSS structure and naming scheme of classes should also be component-driven. Therefore we're using the well-known [BEM naming scheme](http://getbem.com/):

* BEM stands for "Block Element Modifier".
* In our case "Block" equals the "root" element of a Vue component.
* "Element" describes the elements which are **inside** the component.
* "Modifier" is an additional class suitable for adjusting the styling.

```css
/* Block component */
.sw-card {}

/* Element that depends upon the block */
.sw-card__body {}

/* Modifier that changes the style of the block (permanently, static) */
.sw-card--danger {}
.sw-card--large {}

/* State (temporarily, dynamic)  */
.is--selected {}
.is--active {}
```
*CSS structure example using BEM*

```html
<div class="sw-card sw-card--large">
    <div class="sw-card__header">
        <h4 class="sw-card__title">
            Card Title
        </h4>
    </div>
    <div class="sw-card__body">
        Lorem ipsum dolor sit amet
        <div class="sw-card__divider sw-card__divider--primary"></div>
        Lorem ipsum dolor sit amet
    </div>
    <div class="sw-card__footer">
        Card Footer
    </div>
</div>
```
*Markup example for a component*

All CSS sub-classes rely on the root element of the component - even when they are further nested inside the markup. In the above example the root element is `sw-card`. The nested `<h4>` element `sw-card__title` relies on `sw-card` and not on `header`. This approach is recommended in the [BEM documentation](http://getbem.com/faq/#css-nested-elements).


#### Scoping variables for your component

Theming support and modifying the look of the administration is a huge part of the experience. To maintain the ability for full customization, we are scoping the global variables. This enables you to quickly modify the look of the administration by simply using these global variables and additionally lets you fine tune the components' styles  or even whole modules.

- Global variables are used inside the components but will be assigned/re-mapped to component specific variable names. For example, the variable `@color-shopware-blue` could be used for a border color or a background color inside a component.
- As a result, the colours and other stylings can be adjusted individually for each component .
- Component-specific variables are declared at the top of the component's LESS file.
- These should begin with the component's name, for example: `@sw-hello-world-color-background`.


```less
@import "~less/variables";

@sw-hello-world-primary-color-background: @color-shopware-blue;
@sw-hello-world-primary-color-text:       @color-iron;
@sw-hello-world-border-radius:            @border-radius-default;

.sw-hello-world {
    border-radius: @sw-hello-world-border-radius;
  
    and.sw-hello-world__title {
        color: @sw-hello-world-primary-color-text;
        background-color: @sw-hello-world-primary-color-background;
   }  
}
```

####  Importing your stylesheet

Lastly, import the just created LESS file into your component to register the stylesheet within the application:

```diff
// Import the shopware object, more later on
import { Component } from 'src/core/shopware';
import template from './sw-hello-world.html.twig'
+ import './sw-hello-world.less';

// Register your component
Component.register('sw-hello-world', {
    template
});
```

The file structure of your component should like this now:

```
`-- sw-hello-world                  // Folder is named after the component
    |-- index.js                    // Component logic, imports template and stylesheet
    |-- sw-hello-world.html.twig    // Component template
    `-- sw-hello-world.less         // Component stylesheet
```

The component is self-contained within the structure and will therefore work on its own.

### Adding basic functionalities

Now it's time to enhance your first component by adding a basic functionality to it. The component `sw-hello-world` should provide an input field for entering text and a button that triggers the [WebSpeech API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API) making it read out loud the text entered beforehand.

First let's extend the template file:

```vue
<div class="sw-hello-world">
    <div class="sw-hello-world__field">
        <input type="text" v-model="text" placeholder="Enter text...">
    </div>
    <button @click="onSayText" class="sw-hello-world__button">Say "{{ text }}"!</button>
</div>
```

*`sw-hello-world.html.twig` template file for a basic text-to-speech functionality*

We are using Vue.js' [event binding](https://vuejs.org/v2/guide/events.html) and [two-way data binding](https://vuejs.org/v2/guide/forms.html). With these changes in place, we can implement the basic state of the component and the method `onSayText` representing an event handler method. It is triggered everytime the user clicks a button containing the class `sw-hello-world__button`.

```js
import { Component } from 'src/core/shopware';
import template from './sw-hello-world.html.twig'
import './sw-hello-world.less';

Component.register('sw-hello-world', {
    template,

    // Define the initial component state
    data() {
        return {
            text: 'Hello world'
        };
    },

    // Define your custom methods in here
    methods: {
        onSayText() {
            const synth = window.speechSynthesis;
            const utterance = new SpeechSynthesisUtterance(this.text);
            utterance.lang = 'en-GB';

            synth.speak(utterance);
        }
    }
});
```

*`sw-hello-world` component with a basic text-to-speech implementation*

#### Extending the functionality even further

It is possible to extend the component's logic and functionalities even further, by adding an option of selecting the kind of voice reading out the text.

```vue
<div class="sw-hello-world">
    <div class="sw-hello-world__text">
        <input type="text" v-model="text" class="sw-hello-world__text-field">
    </div>
    <div class="sw-hello-world__language">
        <select class="sw-hello-world__language-field" @change="onChangeLanguage">
            <option v-for="(voice, index) in voices" 
                    @key="index" 
                    :value="voice.lang"
                    :selected="voice === activeVoice">
                {{ voice.name }} ({{ voice.lang }})
            </option>
        </select>
    </div>
    <button @click="onSayText">Say "{{ text }}"!</button>
</div>
```

*`sw-hello-world.html.twig` extended example*

In the code example above, we are using many Vue.js' functionalities like [list rendering](https://vuejs.org/v2/guide/list.html), [attributes interpolation rendering](https://vuejs.org/v2/guide/syntax.html#Attributes) and even more are used [event binding](https://vuejs.org/v2/guide/events.html) within the template.

```js
import { Component } from 'src/core/shopware';

Component.register('sw-hello-world', {
    template,
    
    data() {
        return {
            text: 'Hello world!',
            activeVoice: null,
            voices: []
        };
    },

    created() {
        speechSynthesis.onvoiceschanged = () => {
            this.voices = window.speechSynthesis.getVoices();
            this.activeVoice = this.voices.find((voice) => {
                return voice.default;
            });
        }
    },

    methods: {
        onSayText() {
            const synth = window.speechSynthesis;
            const utterance = new SpeechSynthesisUtterance(this.text);
            utterance.lang = this.activeVoice.lang;
            utterance.voice = this.activeVoice;

            synth.speak(utterance);
        },

        onChangeLanguage(event) {
            const lang = event.target.value;
            this.activeVoice = this.voices.find((voice) => {
                return voice.lang === lang;
            });
        }
    }
});
```

*`sw-hello` extended component logic example*

In the component code displayed above, we will have to work around little quirks in the implementation of the [WebSpeech API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API).

### Localization of components

The third-party developer interface allows you to extend the registered locales (default `en-GB` and `de-DE`) and add custom interface translations. The system is based on the [`vue-i18n` plugin](https://kazupon.github.io/vue-i18n/en/). The plugin supports pluralization, placeholders in snippets, HTML formatting, as well as date and number formatting.

```js
import { Locale } from 'src/core/shopware';

Locale.extend('en-GB', {
    "global": {
        "sw-hello-world": {
            "placeholderField": "Enter your text...",
            "buttonSayText": "Say {msg}!"
        }
    }
});
```

*Extending the registered locale `en-GB` with your custom interface translations*

The `vue-i18n` populates the methods `$t()` for basic translations with placeholders, `$tc()` for pluralization, `$d()` for date formatting and `$n()` for number formatting. When an interface translation is not defined in the current locale, the fall-back option is `en-GB`.

Let's enhance the component template with our own interface translations:

```vue
<div class="sw-hello-world">
    <div class="sw-hello-world__field">
        <input type="text" v-model="text" :placeholder="$t('global.sw-hello-world.placeholderField')">
    </div>
    <button @click="onSayText" class="sw-hello-world__button">
        {{ $t('global.sw-hello-world.buttonSayText', { msg: text }) }}
    </button>
</div>
```

*`sw-hello-world.html.twig` with interface translations*

It is even possible to register new locales with the third-party developer interface.

```js
import { Locale } from 'src/core/shopware';

Locale.register('es-ES', {
    "global": {
        "sw-hello-world": {
            "placeholderField": "Ingrese su texto...",
            "buttonSayText": "Decir {msg}!"
        }
    }
});
```

*Register a new locale to the application*

Code splitting is also possible here. To do so, place your interface translations into a JSON file, import it and use it instead of the object we provided in the previous example:

```js
import { Locale } from 'src/core/shopware';
import esESLocales from './snippets/es-ES.json';

Locale.register('es-ES', esESLocales);
```

*Using a JSON file for translations*

```json
{
    "global": {
        "sw-hello-world": {
            "placeholderField": "Ingrese su texto...",
            "buttonSayText": "Decir {msg}!"
        }
    }
}
```

*`es-ES.json` file structure*


## Using the component system

The Shopware administration comes with predefined components which are used throughout the application. You can use these components in your own component / module as well. Our components can be found in the [`administration` repository](https://github.com/shopware/administration) under [`Resources/administration/src/app/components/`](https://github.com/shopware/administration/tree/master/Resources/administration/src/app/component). The following components are available at the time of writing this guide:

- `sw-alert`
    - Notification component supporting different variants based on the type of the notification, e.g. `success`, `error`, `info` and `warning`.
- `sw-avatar`
    - Specialized component which renders an user avatar. If no avatar image is provided, it can be used to display the initials of the user.
- `sw-button`
    - Button with variant support, e.g. `primary`,  `secondary` and `ghost`
- `sw-card`
    - Wrapper element for form elements, usually used on detail pages
- `sw-container`
    - Used to split elements in columns or rows.
- `sw-icon`
    - Specialized component which renders an icon from the administration icon set.
- `sw-sidebar` and `sw-sidebar-item`
    - Used to create a sidebar element for the `sw-grid` component.
- `sw-tabs` and `sw-tabs-item`
    - Provides you with the ability to create a tab panel in your component. It is possible to use a `<router-view>` as the tab panel content.
- `sw-content-menu` and `sw-context-menu-item`
    - Create context menu for the action column in the `sw-grid` for example
- `sw-field`
    - Universal form field component including validation. Used throughout the application to create forms.
- `sw-multi-select`
    - Allows to select multiple items from a data provider as a select field.
- `sw-price-field`
    - Specialized version of `sw-field` to handle prices including tax calculation and currency support.
- `sw-grid` and `sw-grid-column`
    - Providing a data grid including inline editing, column sorting, filtering and more.
- `sw-pagination`
    - Pagination component used in combination with the `sw-grid`
- `sw-page`
    - Module page wrapper component which includes a smart bar for actions buttons, automatically provide you with your module information.
- `sw-color-badge`
    - Small utility component which renders a color badge, used for indication in stock values for example.
- `sw-loader`
    - Loading indicator overlay component which display that a component is currently loading.      

### Default component structure

To provide a consistent outline of a component, we're sorting the available properties and lifecycle hooks. A component's properties should align in an order similar to the following:

1. Template
2. Inject Services / Providers
3. Mixins
4. Pre-Hooks
5. Props
6. Data
7. Computed
8. Watch
9. [Lifecycle methods](https://vuejs.org/v2/guide/instance.html#Lifecycle-Diagram)
   - 1 beforeCreate
   - 2 created
   - 3 beforeMount
   - 4 mounted
   - 5 beforeUpdate
   - 6 updated
   - 7 before Destroy
   - 8 destroyed
10. Methods

The following is a full example of a component following the convention:

```js
import { Component, Mixin } from 'src/core/shopware';
import template from './sw-example.html.twig';
import './sw-example.less';

Component.register('sw-example', {
    template,
    
    inject: ['exampleService'],

    mixins: [
        Mixin.getByName('example')
    ],
    
    beforeRouteEnter(param) {
    	// beforeRouteEnter functionality
    },
    
    props: {
        exampleProp: {
            type: Array,
            required: false,
            default: []
        }
    },

    data() {
        return {
            isExample: true
        };
    },

    computed: {
        example() {
            return this.exampleProp;
        }
    },

    watch: {
        // Watchers
    },
    
    beforeCreate() {
        this.beforeCreateComponent();
    },
    
    // ... all other lifecycle hooks before methods
    
    methods: {
        beforeCreateComponent() {
            // beforeCreate functionality
        }
    }
});
```

*Example component with sorted properties*


### Extending and overriding

Existing components can be extended or even fully overridden. We are providing the application with a system of multiple inheritances for the component logic, as well as the component template.

In the following example we're extending the `sw-customer-detail` page of the `sw-customer` module, creating a new component called `sw-customer-create`.

```js
import { Component } from 'src/core/shopware';
import template from './sw-customer-create.html.twig';

Component.extend('sw-customer-create', 'sw-customer-detail', {
    template
});
```

*Extending a component using the third-party developer interface*

The template file `sw-customer-create.html.twig` only contains the Twig blocks we want to modify. The rest of the template will be derived from the extended component.

```twig
{% block sw_customer_detail_header %}
    <template slot="smart-bar-header">
        <h2 v-if="customerName">{{ customerName }}</h2>
        <h2 v-else>{{ $tc('sw-customer.detail.headlineNewCustomer') }}</h2>
    </template>
{% endblock %}

{% block sw_customer_detail_content_tabs %}
    <sw-tabs class="sw-customer-detail-page__tabs">
        <sw-tabs-item :route="{ name: 'sw.customer.create', params: { id: $route.params.id } }"
                      :title="$tc('sw-customer.detail.tabGeneral')">
            {{ $tc('sw-customer.detail.tabGeneral') }}
        </sw-tabs-item>
    </sw-tabs>
{% endblock %}
```

*Overriding twig blocks*

#### Calling a method from an overridden / extended component

When you are overriding or extending a component, you may want to call methods from an overridden method and then add logic on top of it. For this purpose we added a new property `$super` to the Vue component instance.

```js
import { Component } from 'src/core/shopware';

Component.register('sw-hello-world', {
    data() {
        return {
            title: 'Hello world'
        }
    },
    
    methods: {
        alertTitle() {
            window.alert(this.title);
        }
    }
});
```

*Example component we'll override*

```js
import { Component } from 'src/core/shopware';

Component.override('sw-hello-world', {
    methods: {
        alertTitle() {
            this.$super.alertTitle();
            console.log(`[sw-hello-world-override] ${this.title}`);
        }
    }
});
```

*Example of calling the method `alertTitle` using the `$super` property*

The `$super` property works for both - extended and overridden components and provides the same functionality for both cases.


## Adding data to your component / module

Data is what drives our components. So we made sure, we can provide you with a way to separate the business logic from the component logic, as well as the data logic.

### State modules
In part we're using a global state management with a single state tree. The state management is built on top of [`VueX`](https://vuex.vuejs.org/en/). The main benefit of VueX is that we are having only one point of truth - the state module. No other part of the application is allowed to alter states. A global state management comes in handy when we are dealing with multiple components that simultanously access the same data. For example, the product detail page is separated into multiple components and pages. Consequently every component and page can access the data without having to re-request it.

State modules are registered using the third-party developer interface. These modules are separated in three main parts - an initial state for the module, actions and mutations.

Actions are the methods inside a state module which usually requests data either directly from the API or using the provided API services.

Mutations are the place where the state will be altered. Even actions inside a state module calling a mutation with a certain payload to alter the state. This makes the debugging of the data flow easy. Mutations can furthermore monitored in the [Vue.js devtools browser extension](https://github.com/vuejs/vue-devtools).

The following shows a basic state module. It has the typical structure, we're using it to manage the currently active locale in the application.

```js
import { State } from 'src/core/shopware';

State.register('locale', {
    // Registers the module in its own namespace
    namespaced: true,

    // Initial state, in this case use one property called 'locale' with the value 'en-GB'
    state() {
        return {
            locale: 'en-GB'
        };
    },

    mutations: {
        setLocale(state, locale) {
            const factoryContainer = Shopware.Application.getContainer('factory');
            const localeFactory = factoryContainer.locale;

            // The actual state modification
            state.locale = locale;
            
            localeFactory.setLocale(locale);
        }
    }
});
```

*Example `locale` state module*

State modules can be used for all kinds of purpose, the main problem we want to solve is handling change sets of the data. We're not updating the entire enities in the application, instead we're generating a change set and just sending a diff to the server to perform an update on the entitiy. To do so, we have to keep an `original` and `draft` state and generating the diff of these two state properties.

```js
import { State } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';

State.register('manufacturer', {
    namespaced: true,

    state() {
        return {
            // When entities are loaded, we keep a reference to the original version of the data.
            original: {},
            // For each entity we generate a copy which is the version where all changes are applied.
            draft: {}
        };
    },
    
    getters: {
        manufacturers(state) {
            return state.draft;
        }
    },

    actions: {
        getManufacturerList({ commit }, offset = 0, limit = 25) {
            // Get the manufacturer API service
            const providerContainer = Shopware.Application.getContainer('service');
            const manufacturerService = providerContainer.productManufacturerService;

            // Request the list using the manufacturer API service
            return manufacturerService.getList(offset, limit).then((response) => {
                const manufacturers = response.data;
                const total = response.meta.total;

                // Fire a commit on initManufacturer, it will trigger the `initManufacturer` mutation handler
                manufacturers.forEach((manufacturer) => {
                    commit('initManufacturer', manufacturer);
                });

                return {
                    manufacturers,
                    total
                };
            });
        },
        
        saveManufacturer({ commit, state }, manufacturer) {
            if (!manufacturer.id) {
                return Promise.reject();
            }
            
            // Get the manufacturer API service
            const providerContainer = Shopware.Application.getContainer('service');
            const manufacturerService = providerContainer.manufacturerService;
            
            // Generate the change set
            const changeset = getObjectChangeSet(state.original[manufacturer], manufacturer, 'manufacturer');
            
            // Set the request to the REST API using the API service
            return manufacturerService.updateById(product.id, changeset)
                .then((response) => {
                    // Fire a commit on initManufacturer, it will trigger the `initManufacturer` mutation handler
                    commit('initManufacturer', response.data);
                    return response.data;
                })
                .catch((exception) => {
                   console.error(exception)
                });
        }
    },

    mutations: {
        initManufacturer(state, manufacturer) {
            if (!manufacturer.id) {
                return;
            }

            const originalManufacturer = deepCopyObject(manufacturer);
            const draftManufacturer = deepCopyObject(manufacturer);

            state.original[manufacturer.id] = Object.assign(state.original[manufacturer.id] || {}, originalManufacturer);
            state.draft[manufacturer.id] = Object.assign(state.draft[manufacturer.id] || {}, draftManufacturer);
        }
    }
});
```

The following example shows the state module for the manufacturer module. As you can see here we have the `original` and `draft` in the initial state as empty objects. In the action method called `getManufacturerList()` we're requesting the data for the manufacturer list from the API service called `manufacturerService` and altering the state with the mutation method called `initManufacturer`.

The getter in this state module is similar to a computed property in a Vue.js component. We're able to access the currently active manufacturer drafts using the property key `manufacturers` which will be provided by the getter. You can learn more about getters in the [`VueX` documentation](https://vuex.vuejs.org/en/getters.html).

### Dependency management

We're using [Vue.js' dependency management](https://vuejs.org/v2/api/#provide-inject) using the `provide` and `inject` properties to provide our service providers. To use a service provider like the login or main menu service, you simply use `inject` property.

```js
// Import the shopware object, more later on
import { Component } from 'src/core/shopware';
import template from './sw-hello-world.html.twig'

// Register your component
Component.register('sw-hello-world', {
    template,
    
    inject: [ 'productService' ],
    
    created() {
        console.log(this.productService);
    }
});
```

*Example of using Vue.js dependency management*

The following service providers were available at the time of writing this guide:

* `applicationService`
* `categoryService`
* `contextRuleService`
* `countryService`
* `currencyService`
* `customerGroupService`
* `customerService`
* `mediaService`
* `orderDeliveryService`
* `orderLineItemService`
* `orderService`
* `orderStateService`
* `paymentMethodService`
* `productManufacturerService`
* `productService`
* `shippingMethodService`
* `shopService`
* `taxService`

These services are so called API services which are an abstraction layer of the REST API we're having in place. We're providing a couple specialized services as well:

* `menuService`
* `jsonApiParserService`
* `loginService`

It is also possible to register your own service provider using the third-party developer interface:

```js
Shopware.Application.addServiceProvider('exampleService', () => {
    return new ExampleService();
});
```

*Registering your own service provider* 

```js
// Import the shopware object, more later on
import { Component } from 'src/core/shopware';
import template from './sw-hello-world.html.twig'

// Register your component
Component.register('sw-hello-world', {
    template,
    
    inject: [ 'exampleService' ],
    
    created() {
        console.log(this.exampleService);
    }
});
```

*Using your custom service provider in a component*

#### Mixins

We're using mixins as convenience services for the API services which are injecting additional functionality to your component. We're using [Vue.js' mixins property](https://vuejs.org/v2/guide/mixins.html) to provide these services.

```js
// Import the shopware object, more later on
import { Component, Mixin } from 'src/core/shopware';
import template from './sw-hello-world.html.twig'

// Register your component
Component.register('sw-hello-world', {
    template,
    
    mixins: [
        Mixin.getByName('notification')
    ]
    
    created() {
        this.createNotificationSuccess({
            title: 'Yay', 
            message: 'You did something correctly! Amazing, huh?'
        });
    }
});
```

*Example of using a mixin in your component*

The following mixins are available at the time of writing this guide:

* `product`
* `customer`
* `customerList`
* `productList`
* `manufacturerList`
* `taxList`
* `currencyList`
* `contextRuleList`
* `validation`
* `notification`
* `applicationList`
* `customerGroupList`
* `paymentMethodList`

### Creating your own mixin and state module

As mentioned before we're using Vue.js' mixins as convenience services for the API services. These services usually containing additional logic and initial state for the component, which makes it a breeze to work with the REST api. Mixins are the place where you can find the business logic for a certain entity. Mixins are separated into a detail and list mixin for example `product` and `productList`. What mixins you have to use depends on the fact if you want to implement a list or detail view of an entity. Mixins are not using the API services directly - they're using state modules.

The third-party developer interface provides the ability to register your own mixins and state modules. You need to write your own when your extension provides a new REST API end point.

```js
import { Mixin } from 'src/core/shopware';

Mixin.register('taxList', {
    // Additional initial state for the component
    data() {
        return {
            taxes: [],
            totalTaxes: 0,
            limit: 25,
            total: 0,
            isLoading: false
        };
    },

    // When the component with this mixin is mounted, get the list of taxes
    mounted() {
        this.getTaxList();
    },

    methods: {
        // Additional methods to request the tax list from the API endpoint using the state module
        getTaxList() {
            this.isLoading = true;
            
            // Calling the method `getTaxList` in the state module `tax`
            return this.$store.dispatch('tax/getTaxList', this.offset, this.limit).then((response) => {
                this.totalTaxes = response.total;
                this.taxes = response.taxes;
                this.isLoading = false;

                return this.taxes;
            });
        }
    }
});
```
*Example of a mixin, in this case for the API endpoint `taxList`*

State modules are an additional layer on top of the API services which are the place where you can find additional data logic. They're taking care of creating drafts for the change set generation, handling deletions of enitity deletions, requesting data using the API services and initializing empty entities.

```js
import { State } from 'src/core/shopware';
import { deepCopyObject } from 'src/core/service/utils/object.utils';

State.register('tax', {
    namespaced: true,

    state() {
        return {
            // When entities are loaded, we keep a reference to the original version of the data.
            original: {},
            // For each entity we generate a copy which is the version where all changes are applied.
            draft: {}
        };
    },

    getters: {
        tax(state) {
            return state.draft;
        }
    },

    actions: {
        getTaxList({ commit }, offset = 0, limit = 25) {
            const providerContainer = Shopware.Application.getContainer('service');
            const taxService = providerContainer.taxService;

            return taxService.getList(offset, limit).then((response) => {
                const taxes = response.data;
                const total = response.meta.total;

                taxes.forEach((tax) => {
                    commit('initTax', tax);
                });

                return {
                    taxes,
                    total
                };
            });
        }
    },

    mutations: {
        initTax(state, tax) {
            if (!tax.id) {
                return;
            }

            const originalTax = deepCopyObject(tax);
            const draftTax = deepCopyObject(tax);

            tax.isLoaded = true;
            state.original[tax.id] = Object.assign(state.original[tax.id] || {}, originalTax);
            state.draft[tax.id] = Object.assign(state.draft[tax.id] || {}, draftTax);
        }
    }
});
```

*Example of a state module, in this case the `tax` state module*

#### Fetching data from the API and API Services

Let us take a closer look on the method `getTaxList`.

```js
getTaxList({ commit }, offset = 0, limit = 25) {
    const providerContainer = Shopware.Application.getContainer('service');
    const taxService = providerContainer.taxService;

    return taxService.getList(offset, limit).then((response) => {
        const taxes = response.data;
        const total = response.meta.total;

        // Initializing the tax item and adding it to the state
        taxes.forEach((tax) => {
            commit('initTax', tax);
        });

        return {
            taxes,
            total
        };
    });
}
```

In there we're using the application-wide dependency management using [Bottle.js](https://github.com/young-steveo/bottlejs). The application exposes the dependency injection containers using the third-party developer interface. This way we're having access to all available factories, initializers and service providers. We're providing three containers - `factory`, `init` and `service`.

```js
Shopware.Application.getContainer('factory');
Shopware.Application.getContainer('init');
Shopware.Application.getContainer('service');
```

*Example access using the application-wide dependency containers*

In the example we're getting the `service` container which contains all service providers. In the next line we're accessing the `taxService` from the container to have access to the API service.

It is also possible to register your own API service when your extension provides a new REST API endpoint. API services extending a basic API service to provide functionality every service needs for example setting the correct HTTP request headers, `getById`, `updateById`, `getList`, handling the HTTP response, parsing the response data using the [JSON API](http://jsonapi.org/) parser and much more.

```js
import ApiService from 'src/core/service/api/api.service';

/**
 * Gateway for the API end point "example"
 * @class
 * @extends ApiService
 */
class ExampleService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'example') {
        super(httpClient, loginService, apiEndpoint);
    }
    
    // Your custom methods...
    getListById(id = null) {
        if (!id) {
            return Promise.reject();
        }
        
        // Fire the request
        return this.httpClient.get(`/example?uuid=${id}`).then((response) => {
            const items = response.data;
            const total = response.meta.total;
            
            return {
                items,
                total
            };
        }).catch((err) => {
            console.error(`[API][exampleService.getListById]: ${err}`);
        });
    } 
}

export default ExampleService;
```

*Creating your own API service*

The injected HTTP client is a configured [Axios instance](https://github.com/axios/axios) which provides you with an easy-to-use interface for HTTP requests using [promises](https://developer.mozilla.org/de/docs/Web/JavaScript/Reference/Global_Objects/Promise).

```js
// Register your service provider
Shopware.Application.addServiceProvider('exampleService', (serviceContainer) =>() {
    const initContainer = application.getContainer('init');
    return new ExampleService(initContainer.httpClient, container.loginService);
});
```

*Registering your own service as a service provider in the application*

## Register a module in the administration

Modules are the bread and butter for the application. They're using the components and composing them to a full page for the administration interface. Modules are having a route to access them and usually at least one main menu entry to access them using the main menu. The registration of modules are following our module manifast:

```js
import { Module } from 'src/core/shopware';

Module.register('<module-name>', {
    type: '<type>',
    color: '<hex-code>',
    icon: '<icon-name>',
    description: '<short-description>',
    version: '<semver>',
    
    routes: {
        // More later on
    },
    
    navigation: [
        // More later on
    ]
});
```

*Module manifest syntax*

- `<module-name>`
    - Module names are separated in two parts. The first part is the developer prefix and the second part is the module name, e.g. `sw-product`, `sw-login` or `sw-customer`.
- `<type>`
    - The type of the module. Available types are `core` and `plugin` (default).
- `<hex-code>`
    - We're having a colour-coded routing system in place, so that modules for a certain section getting a defined colour. Either a 3 or 6 character long colour hex code is valid.
- `<icon-name>`
    - Name of an icon from the shopware svg icon file.
- `<short-description>`
    - A short description of the module functionality in English,
- `<semver>`
    - A semver based version number for the module. More information about [semver can be found here](https://semver.org/).

### Routing and routes definition

In the Shopware administration we're using the [`vue-router` plugin](https://router.vuejs.org/en/) which is nicely integrated into Vue.js and abstracted using our module manifest. Please keep in mind that your module isn't accessible in the administration interface when no route is defined. The module factory which registers the module will throw warnings in the developer console. 

```js
import { Module } from 'src/core/shopware';

Module.register('sw-example', {
    type: 'core',
    
    routes: {
        index: {
            component: 'sw-example-index',
            path: 'index'
        }
    }
});
```
*Example of registering a route*

The following route will be rendered in the administration: `http://example.shop/admin#core/sw/example/index`.

#### Aliases for routes

The [`vue-router` plugin](https://router.vuejs.org/en/) supports route aliases. The module manifest supports it too:

```js
routes: {
    index: {
        component: 'sw-example-index',
        path: 'index',
        alias: 'list'
    }
}
```
*Defining an alias for a route*

The following two routes will be generated:

* `http://example.shop/admin#core/sw/example/index`
* `http://example.shop/admin#core/sw/example/list`

For more information, head over to the [redirect documentation of the `vue-router` plugin](https://router.vuejs.org/en/essentials/redirect-and-alias.html).

### Main Menu Navigation entry

To access the module using the main menu in the administration we have to register an main menu entry in the module manifest. The property `navigation` accepts an array of menu entries:

```js
import { Module } from 'src/core/shopware';

Module.register('sw-example', {
    type: 'core',
    
    routes: {
        // Will be the named route `sw.example.index`
        index: {
            component: 'sw-example-page-index', // Component name, first argument for Component.register
            path: 'index'
        }
    },
    
    navigation: [{
        path: 'sw.example.index',
        label: 'Example module',
        color: '#dd4800',
        icon: 'default-avatar-multiple'
    }]
});
```
*Registering a main menu entry*

The `path` property is important here cause it relates to the routes configuration. The path has to be the named route of the routes configuration. The name is based on the module name and the routes key, e.g. `sw.example.index`. Please keep in mind the hyphen in the module name will be replaced with a dot during the initialization of the route.

Same as the module a navigation entry can have a colour and an icon.

It is also possible to nest menu entries. Just use the `parent` property to define the parent named route:

```js
navigation: [{
    path: 'sw.example.index',
    label: 'Example module',
    color: '#dd4800',
    icon: 'default-avatar-multiple'
}, {
    path: 'sw.example.create',
    label: 'Add example',
    color: '#dd4800',
    icon: 'default-avatar-multiple',
    parent: 'sw.example.index'
}]
```

*Nested navigation entriesexample*

### Pages

Page components are nothing else than normal components. They get a special role cause they're used as the route component and using a special component called `sw-page` which reads out the module manifest and provides it to the component using the meta information in the route. You can access these information in a component using the following code:

```js
import { Component } from 'src/core/shopware';
import template from './sw-example-page-index.html.twig'

// Register your component
Component.register('sw-example-page-index', {
    template,
    
    created() {
        console.log(this.$route.meta);
    }
});
```

When you're creating a template for a page component please use the component `sw-page` as the root element of your component:

```twig
<sw-page class="sw-example-page-index">
    <template slot="smart-bar-header">
        Hello World
    </template>
    
    <template slot="smart-bar-actions">
        <sw-button variant="primary">
            Say hello world!
        </sw-button>
    </template>
    
    <template slot="content">
        ...your module content...
    </template>
</sw-page>
```
*Page component template example*

Back to the module manifest, go to the route component definition and use your component `sw-hello-world-page-index` here:

```js
import { Module } from 'src/core/shopware';

Module.register('sw-example', {
    type: 'core',
    
    routes: {
        index: {
            component: 'sw-example-page-index', // Component name, first argument for Component.register
            path: 'index'
        }
    },
    
    navigation: [{
        path: 'sw.hello.index',
        label: 'Hello world module',
        color: '#dd4800',
        icon: 'default-avatar-multiple'
    }]
});
```

*Using your page component*

## The third-party developer interface

The third-party developer interfaces gives you access to almost all parts of the application and therefore enables you to do pretty much all what you want to do.

The following features and methods are available to you:

* `Module` - Represents the module factory
    * `register`
        * Registers a new module
* `Component` - Represents the component facotry
    * `register`
        * Registers a new component
    * `extend`
        * Extends a registered component
    * `override`
        * Overrides a registered component
    * `build`
        * Merges the component configuration including overrides and template overrides and returns the final component configuration object to create a Vue.js component.
    * `getTemplate`
        * Wrapper method for the `Template.getRenderedTemplate` method.
* `Template` - Represents the thin layer for Twig.js
    * `register`
        * Registers a new template
    * `extend`
        * Extends a registered template
    * `getRenderedTemplate`
        * Returns the complete rendered template string of the component.
    * `find`
        * Tries to find a `<template>` tag where the attribute `component` is equal to the name of the component in the `document`.
    * `findOverride`
        *  Tries to find a `<template>` tag where the attribute `override` is equal to the name of the component in the `document`.
* `Application` - The application bootstrapping which takes care of the dependency management and bootstrapping of the application
    * `addFactory`
        * Adds a factory to the application. A factory creates objects for the domain. The factory will be registered in a nested DI container.
    * `addFactoryDecorator`
        * Registers a decorator for either every factory in the container or a defined one.
    * `addFactoryMiddleware`
        * Registers a factory middleware for either every factory in the container or a defined one. 
    * `addInitializer`
        * Adds an initializer to the application. An initializer is a necessary part of the application which needs to be initialized before we can boot up the application. The initializer will be registered in a nested DI container.
    * `addInitializerDecorator`
        * Registers a decorator for either every initializer in the container or a defined one.
    * `addInitializerMiddleware`
        * Registers an initializer middleware for either every initializer in the container or a defined one.
    * `addServiceProvider`
        * Registers optional services & provider for the application. Services are usually API gateways but can be a simple service. The service will be added to a nested DI container.
    * `addServiceProviderMiddleware`
        * Registers a service provider middleware for either every service provider in the container or a defined one.
    * `getContainer`
        * Returns all containers. Use this method if you're want to get initializers in your services for example. 
* `State` - Abstraction layer for VueX
    * `register`
        * Registers a new state module for the application.
* `Mixin` - Thin layer for mixin objects
    * `register`
        * Registers a new mixin for the application
    * `getByName`
        * Returns a mixin based on its name.
* `Filter` - Thin layer for Vue.js filters and variable modifiers
    * `register`
        * Registers a new filter for the application
    * `getByName`
        * Returns a filter based on its name.
* `Locale` - Abstraction layer to register and extend interface translations
    * `register`
        * Registers a new locale for the application
    * `getByName`
        * Returns a locale based on its name.
    * `extends`
        * Extends a registered locale with new translations 
* `Entity`
    * `addDefinition`
        * Adds a new entity definition for the application.
    * `getDefinition`
        * Returns a entity definition based on its name.
    * `getDefinitionRegistry`
        * Returns the whole registry of all registered entity definitions
    * `getRawEntityObject`
        * Returns the unparsed entity scheme object
    * `getRequiredProperties`
        * Returns the required properties for an entitiy. Used for validations.  


## Webpack, tooling and packing your module
Webpack's main purpose is to bundle JavaScript files for usage in a browser. It's also possible to transform, bundle and package all kind of resources for example stylesheets or images. To do so it builds a dependency graph that include every module of the application and then bundles all of those modules into one or more small bundles.

We're using Webpack to bundle all the parts of the administration interface into one handy bundled file for deployment but that's not all. We're using it to expose parts of the application into the global scope to provide an unified interface to interact with the application on the beginner user level. The style definition is written in [LESS](http://lesscss.org/) which will be transformed to plain CSS using Webpack too.

Please keep in mind that Webpack is only used as a tool for development. The application for the administration gets build and is delivered as one complete package. No compiling or build process is necessary in a normal installation of Shopware.

Webpack has 4 core concepts which enables you to customize it to your needs and process every tasks you want: **entry, output, loaders & plugins.** The core concepts of Webpack enable you as a third-party developer to use all the same tools we are using for developing. Webpack is able to identify the active plugins in the shop and processes the plugins JavaScript and LESS files and dumps out a compiled version into the plugin directory ready for deployment to the community store.

To compile your extension, please use the command `./psh.phar administration:build`. We're hooking your extension into our webpack configuration as another entry point, therefore we're getting a separeted bundle and copying the necessary CSS & JS files to your plugin directory.

To use Webpack with your [PhpStorm](https://www.jetbrains.com/phpstorm/) installation, head over to "Settings" -> "Languages & Frameworks" -> "JavaScript" -> "Webpack" and point the "webpack configuration file" to `<your-project-root>/vendor/shopware/platform/src/Administration/Resources/administration/build/webpack.base.conf.js` to get alias resolving and more.

### Tooling
The usage of Webpack and the Vue.js framework offers very helpful tooling for developers which can facilitate your daily work.

* Hot Module Reloading
    * For developing you can spawn a dev-server right from your CLI. This will also start a file watcher which will update the page directly when you make any changes. The nice thing about this is, that the application will keep its state also after refreshing. So you automatically stay at the same place where you're working at. The watcher also offers automatic linting using [ESLint](https://eslint.org/) and will show a nice overlay with helpful error messages. The hot module reloading mode can be started up using the command `./psh.phar administration:watch` in your project root.
* Vue.js developer console
    * The Vue.js framework offers a nice extension for the chrome developer console. Here you have a reference to the original component structure and can inspect each component to get live information about its state, events and several other information. This can be a really helpful tool during development. You can get it from the [official GitHub repository](https://github.com/vuejs/vue-devtools).
* Storybook component documentation system
    * Storybook is tool which allows us to create an interactive component documentation. It shows you with slots are available and how to use a component. To generate the storybook documentation on your own, you can use the command `./psh.phar administration:storybook-generate`. Storybook comes with a hot module reloading mode which can be triggered using the command `/psh.phar administration:storybook-watch`.
* JsDoc
    * We're using JsDoc v3 as our API documentation system. If you want to generate the documentation, please run the command `./psh.phar administration:generate-api-docs` in your project files.
* Unit Tests
    * The core factories are covered with unit tests using the [Karma Test Runner](https://karma-runner.github.io/2.0/index.html). The test specs itself are based on [Chai](http://www.chaijs.com/) and [Mocha](https://mochajs.org/). To run the unit test on your own, please use the command `./psh.phar administration:unit`. Karma also supports a watch mode which can be triggered using the command `./psh.phar administration:unit-watch`.
