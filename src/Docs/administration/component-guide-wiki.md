
## Introduction
Components are the fundamental part of the Shopware\Core administration. Literally everything is a component. Shopware already provides a bunch of useful components. These can also be extended or overridden if they do not fit the use case. Shopware has a thin layer architecture which provides interfaces to register new components or to extend and override existing components. 
## Creating a new component
### File structure
For a component we have to create a new folder with some files. For a better overview, especially in complex components we are seperating the component in different files. The `index.js` is always the main file for a component. It contains the whole logic and imports the others files. Next we have `.hmtl.twig` file which contains the components HTML template. The `.less` file contains all of the styling. The file structure of a component could look like this:
```
`-- sw-hello-world                  // Folder is named after the component
    |-- index.js                    // Component logic, imports template and stylesheet
    |-- sw-hello-world.html.twig    // Component template
    |-- sw-hello-world.less         // Component stylesheet
```

At least the `index.js` is required. The template and less file are only necessary if we provide content for them. We could also create a mixin if we do not need any templating **LINK TO CREATING A MIXIN?**.

### Component JavaScript
We start the new component by creating the `index.js`, which is the main entrance to the component:
`sw-hello-world/index.js`
```js
// Import the shopware object
import { Component } from 'src/core/shopware';
// Import the twig template
import template from './sw-hello-world.html.twig';
// Import the less style file
import './sw-hello-world.less';

// Register your component
Component.register('sw-hello-world', {
    // Use the imported template for the new vue component
    template
});
```

In the code example above, we are registering a new component in the application. In order to do so we have to import the `Component` object from the third-party interface. The interface takes care of delivering the component to the application and thereby registering it as a Vue.js component. This provides and injects a service provider and bootstraps it. We also import 
the template and use it in the newly created component. The less file gets automatically compiled in the building process when it is imported.

### Template
It is possible to use inline templating for the component for example with template literals, but for a better overview, especially in more complex components we create a new file for the template:
`sw-hello-world/sw-hello-world.html.twig`
```twig
{% block sw_hello_world %}
    <div class="sw-hello-world">
        {% block sw_hello_world_headline %}
            <h1>Hello world!</h1>
        {% endblock %}
    </div>
{% endblock %}
```
Since we are also using the twig templating engine for extending functionalities we can create blocks which can be overridden by other developers / components. More information on this can be found in the Extending / Overriding section of this article.
Note that Vue.js requires **exactly one** component "root" element. As a convention we are utilizing a `<div>` element carrying the component name as its class name.

### Styling
We are using LESS for styling the component. This provides options such as variables, nesting of selectors, mixins, inline media queries and much more. To learn more about LESS and it's functionalities, there is an [in-depth guide](http://lesscss.org/features/) on the [project homepage](http://lesscss.org/).

In your component's folder create a new LESS-file named after the component name , e.g. `sw-hello.less`.

Import our global variables to the newly created file in order to achieve a consistent look for your component:  
`sw-hello-world/sw-hello-world.less`
```less
@import "~less/variables";

.sw-hello-world {
    // ...
}
```
Make sure to take a look at our naming convention in this article **LINK TO BEM**.

## Adding functionalities
### Data binding and events

Now we enhance the component by adding a basic functionality to it. The component `sw-hello-world` should provide an input field for entering text and a button that triggers the [WebSpeech API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API) making it read out loud the text entered beforehand.

First let's extend the template file:  
`sw-hello-world/sw-hello-world.html.twig`
```vue
{% block sw_hello_world %}
  <div class="sw-hello-world">
    {% block sw_hello_world_headline %}
        <h1>Hello world!</h1>
    {% endblock %}
    <div class="sw-hello-world__field">
      <input type="text" v-model="text" placeholder="Enter text...">
    </div>
    <button @click="onSayText" class="sw-hello-world__button">Say "{{ text }}"!</button>
  </div>
{% endblock %}
```

We are using Vue.js' [event binding](https://vuejs.org/v2/guide/events.html) and [two-way data binding](https://vuejs.org/v2/guide/forms.html). With these changes in place, we can implement the basic state of the component and the method `onSayText` representing an event handler method. It is triggered everytime the user clicks the button containing.  
`sw-hello-world/index.js`
```js
import { Component } from 'src/core/shopware';
import template from './sw-hello-world.html.twig'
import './sw-hello-world.less';

Component.register('sw-hello-world', {
    template,

    // The initial component state
    data() {
        return {
            text: 'Hello world'
        };
    },

    // Custom methods
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

### List rendering and event binding

To extend the component's logic and functionalities even further, we add the option to select the kind of voice, which is reading out the text.  
`sw-hello-world/index.js`
```js
import { Component } from 'src/core/shopware';
import template from './sw-hello-world.html.twig';
import './sw-hello-world.less';

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
        };
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

In the component code displayed above, we will have to work around little quirks in the implementation of the [WebSpeech API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API).

`sw-hello-world/sw-hello-world.html.twig`
```html
{% block sw_hello_world %}
    <div class="sw-hello-world">
        {% block sw_hello_world_headline %}
            <h1>Hello world!</h1>
        {% endblock %}
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
{% endblock %}
```
In the code example above, we are using some Vue.js' functionalities like [list rendering](https://vuejs.org/v2/guide/list.html), [attributes interpolation rendering](https://vuejs.org/v2/guide/syntax.html#Attributes) or [event binding](https://vuejs.org/v2/guide/events.html) within the template.

## Localization
The third-party developer interface allows to extend the registered locales (default `en-GB` and `de-DE`) and add custom interface translations. The system is based on the [`vue-i18n` plugin](https://kazupon.github.io/vue-i18n/en/). The plugin supports pluralization, placeholders in snippets, HTML formatting, as well as date and number formatting.

In this example we add some english translations for our component
`sw-hello-world/index.js`
```js
import { Component, Application } from 'src/core/shopware';
// ...

Application.addInitializerDecorator('locale', (localeFactory) => {
    localeFactory.extend('en-GB', {
        global: {
            'sw-hello-world': {
                placeholderField: 'Enter your text...',
                buttonSayText: 'Say {msg}!'
            }
        }
    });
});

Component.register('sw-hello-world', {
    //...
})
```

The `vue-i18n` populates the methods `$t()` for basic translations with placeholders, `$tc()` for pluralization, `$d()` for date formatting and `$n()` for number formatting. When an interface translation is not defined in the current locale, the fall-back option is `en-GB`.

Let's enhance the component template with our own interface translations:  
`sw-hello-world/sw-hello-world.html.twig`
```vue
{% block sw_hello_world_headline %}
    <div class="sw-hello-world">
        {% block sw_hello_world_headline %}
            <h1>Hello world!</h1>
        {% endblock %}
        <div class="sw-hello-world__field">
            <input type="text" v-model="text" :placeholder="$t('global.sw-hello-world.placeholderField')">
        </div>
        <button @click="onSayText" class="sw-hello-world__button">
            {{ $t('global.sw-hello-world.buttonSayText', { msg: text }) }}
        </button>
    </div>    
{% endblock %}
```

It is even possible to register new locales with the third-party developer interface.
`sw-hello-world/index.js`
```js
import { Component, Application } from 'src/core/shopware';
// ...

Application.addInitializerDecorator('locale', (localeFactory) => {
    localeFactory.register('es-ES', {
        global: {
            'sw-hello-world': {
                placeholderField: 'ngrese su texto...',
                buttonSayText: 'Decir {msg}!'
            }
        }
    });
});

Component.register('sw-hello-world', {
    //...
})
```
Code splitting is also possible here. To do so, place your interface translations into a JSON file, import it and use it instead of the object we provided in the previous example:

`sw-hello-world/index.js`
```js
import { Component, Application } from 'src/core/shopware';
import esESLocales from './snippets/es-ES.json';
// ...

Application.addInitializerDecorator('locale', (localeFactory) => {
    localeFactory.register('es-ES', esESLocales);
});

Component.register('sw-hello-world', {
    //...
})
```
The snippet file could look like this:  
`sw-hello-world/snippets/es-ES.json`
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

## Extending components
Existing component can be easily extended. We are providing the application with a system of multiple inheritances for the component logic, as well as the component template.

In the following example we will create a new component `sw-hello-foo` which extends from our `sw-hello-world` example and changes some functionalities.

The file structure is the same as for creating a new component:
```
`-- sw-hello-foo                  
    |-- index.js                y
    |-- sw-hello-foo.html.twig
    |-- sw-hello-foo.less
```

`sw-hello-foo/index.js`
```js
import { Component } from 'src/core/shopware';
import template from './sw-hello-foo.html.twig';

Component.extend('sw-hello-foo', 'sw-hello-world', {
    template,

    methods: {
        alertElement() {
            console.log('I can be included by using <sw-hello-foo></sw-hello-foo>');
        }
    }
});
```

The template file `sw-hello-foo.html.twig` only contains the Twig blocks we want to modify. The rest of the template will be derived from the extended component.

```twig
{% block sw_hello_world_headline %}
    <h1>Foo content before</h1>

    <button @click="alertElement">
        Alert
    </button>

    {% parent %}
{% endblock %}
```
The `{% parent %}` call renders the content of the parent template.

## Overriding components
It is also possible to override a component. Here is a small example:
`sw-hello-bar/index.js`
```js
import { Component } from 'src/core/shopware';

Component.override('sw-hello-foo', {
    template: '',

    methods: {
        alertElement() {
            console.log('You still have to use the <sw-hello-foo> Element');
        }
    }
});
```

## Calling a method from an overridden / extended component

When you are overriding or extending a component, you may want to call methods from an overridden method and then add logic on top of it. For this purpose we added a new property `$super` to the Vue component instance.

`sw-hello-bar/index.js`
```js
import { Component } from 'src/core/shopware';

Component.override('sw-hello-foo', {
    template: '',

    methods: {
        alertElement() {
            this.$super.alertElement();
            console.log('You still have to use the <sw-hello-foo> Element');
        }
    }
});
```
The `$super` property works for both - extended and overridden components and provides the same functionality for both cases.

## Dependency management

We're using [Vue.js' dependency management](https://vuejs.org/v2/api/#provide-inject) using the `provide` and `inject` properties to provide our service providers. To use a service provider like the login or main menu service, you simply use the `inject` property.

Example:
```js
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

The following service providers are available

* `shopService`
* `catalogService`
* `integrationService`
* `categoryService`
* `productService`
* `productManufacturerService`
* `orderService`
* `orderLineItemService`
* `orderDeliveryService`
* `orderStateService`
* `customerService`
* `customerAddressService`
* `customerGroupService`
* `paymentMethodService`
* `shippingMethodService`
* `countryService`
* `currencyService`
* `taxService`
* `ruleService`
* `mediaService`
* `salesChannelService`
* `salesChannelTypeService`
* `searchService`
* `languageService`
* `localeService`
* `userService`

These services are so called API services which are an abstraction layer of the REST API we're having in place. We're providing a couple specialized services for certain tasks as well:

* `menuService`
* `jsonApiParserService`
* `loginService`
* `validationService`

### Register own service
It is also possible to register your own service provider using the third-party developer interface.  
Example:
```js
Shopware.Application.addServiceProvider('exampleService', () => {
    return new ExampleService();
});
```
The example service can then be injected the same way:

```js
import { Component } from 'src/core/shopware';
import template from './sw-hello-world.html.twig'

Component.register('sw-hello-world', {
    template,
    
    inject: [ 'exampleService' ],
    
    created() {
        console.log(this.exampleService);
    }
});
```

## Mixins

We're using mixins to injecting additional functionality to your component.Therefore we are using [Vue.js' mixins property](https://vuejs.org/v2/guide/mixins.html).

Example:
```js
import { Component, Mixin } from 'src/core/shopware';
import template from './sw-hello-world.html.twig'

// Register your component
Component.register('sw-hello-world', {
    template,
    
    mixins: [
        Mixin.getByName('notification')
    ],
    
    created() {
        this.createNotificationSuccess({
            title: 'Nice', 
            message: 'You successfully used a mixin!'
        });
    }
});
```

The following mixins are available:

* `listing`
* `validation`
* `notification`
* `drag-selector`

### Creating new mixin
As a third party developer you can create an own mixin for reusable component logic.
Example:
```js
import { Mixin } from 'src/core/shopware';

Mixin.register('exampleMixin', {
    // ...
});
```

# Component conventions

## Table of contents

1. Component conventions
   - General conventions
   - Component properties order
   - Lifecycle hooks
   - Component variants
   - Class and style bindings
2. Markup and LESS
   - BEM
   - LESS variables
   - LESS structure for nested components
   - Component Twig blocks
   

## General conventions
 - Each component should be as slim as possible.
 - The component stands for its own and fulfills only one purpose. It is better to make multiple smaller components than putting too much complexity into a single component.
 - The component contains not much logic. Complex logic is handled by the modules.
 - The component is documented inside the storybook.
   
## Component properties order

The properties of a component should have the following order:

1. Template
2. Inject Services / Providers
3. Mixins
4. Pre-Hooks
5. Props
6. Data
7. Computed
8. Watch
9. <a href="https://vuejs.org/v2/guide/instance.html#Lifecycle-Diagram">Lifecycle methods</a>
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

```
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
    }
    
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
        }
    },

    computed: {
        example() {
            return this.exampleProp;
        }
    },

    watch: {
        // Watchers
    }
    
    beforeCreate() {
        this.beforeCreateComponent();
    }
    
    // ... all other lifecycle hooks before methods
    
    methods: {
        beforeCreateComponent() {
            // beforeCreate functionality
        }
    }
});
```

## Lifecycle hooks

Instead of using the Vue.js lifecycle hook methods directly, the desired functionality should be placed inside a separate method.
This method is named like the lifecycle hook and has an additional `Component` at the end of the method name.

**Lifecycle hooks example:**

```
mounted() {
    this.mountedComponent();
}

beforeUpdate() {
    this.beforeUpdateComponent();
}

methods: {
    mountedComponent() {
        // mounted functionality
    }
    
    beforeUpdateComponent() {
        // beforeUpdate functionality
    }
}
```

## Component variants
Some components provide different variants or versions of itself. For example the `<sw-button>` component comes with
different variants like `primary` or `ghost`. When there are more than two variants of a component this could be handled
by a single property like `variant` or `size`. 

Using multiple Boolean properties which do kind of the same thing should be avoided when possible. 
Whether you wan't to use Booleans or something like `variant` depends on the use case.
When you want combine multiple variants with each other, Booleans may be the better choice.

**Component variants example:**

```
<!-- Default button (no variant) -->
<sw-button>Button text</sw-button>

<!-- Primary button -->
<sw-button variant="primary">Button text</sw-button>

<!-- Ghost button with size large -->
<sw-button variant="ghost" size="large">Button text</sw-button>
```

When a certain behavior should be active or inactive, a Boolean property is of course the right way:

**Component boolean example:**

```
<!-- Button with isLoading flag -->
<sw-button isLoading>Button text</sw-button>
```

## Class and style bindings

When creating a default component like `sw-card` or `sw-button` the class and style bindings should not be directly in 
the template. This pattern can often be found in the Vue.js documentation or different tutorials.

Instead there should be a computed property which contains all logic for toggling classes or handle inline styles.
The computed prop should be named like the component in camelCase with the word "Classes" or "Styles" as a suffix - 
depending if there should be CSS classes or inline styles.

```
cardClasses() {
    return {
        'sw-card--slim': this.slim,
        'sw-card--dark': this.dark,
        [`sw-card--${this.variant}`]: this.variant,
    };
}
```

In the template we only have to bind the computed prop now:

```
<div class="sw-card" :class="cardClasses" :style="cardStyles">
```

This makes it easier for other developers to override CSS classes or inline styles because
no root level Twig blocks have to be overridden.



## BEM

Because the shopware administration is a component based application with reusable elements, the CSS structure is also component-driven. The Markup and CSS of the administration is using BEM as a naming convention. 

* BEM stands for "Block Element Modifier".
* In our case "Block" would be equal to the root element of a Vue component.
* "Element" describes the elements which are **inside** the component.
* "Modifier" is an additional class which can adjust the styling.
* Further reading: <a href="http://getbem.com/">getbem.com</a>

**CSS Example:**

```
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

**Markup Example for a component:**

```
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

All CSS sub-classes rely on the root element of the component &ndash; even when they are nested further inside the markup. In the above example the root element is `sw-card`. The nested `<h4>` element `sw-card__title` relies on `sw-card` and not on `header`. This approach is recommended in the <a href="http://getbem.com/faq/#css-nested-elements">BEM documentation</a>.

## LESS variables

### General variable naming convention

- Each variable should be prefixed with its purpose in a meaningful way.<br>
  Example: Variables which contain HEX or rgba values should begin with `@color- ...`.
- All variables should be kebab-case. Please avoid using camelCase or snake_case when possible.

```
@color-primary:            #f00;
@z-index-dialog:           9000;
@border-radius-default:    6px;
@color-box-shadow-default: rgba(0, 0, 0, 0.2);
@width-content:            1200px;
@size-avatar-default:      50px;
@font-family-default:      'Source Sans Pro', Arial, sans-serif;

```

### Global variables

- In addition to the components own LESS files, the administration is also offering global variables to provide an easy way to develop styling.
- The global color variables match the color names in our design guidelines.

**Global variables.less example:**

```
// Primary
@color-shopware-blue:     #189EFF;
@color-biscay:            #16325C;
@color-deep-cove:         #303A4F;
@color-crimson:           #DE294C;
@color-pumpkin-spice:     #FFB75D;

// Neutrals
@color-kashmir:           #54698D;
@color-iron:              #FAFBFC;
@color-cadet-blue:        #E8F6FF;

...
```

- The global variables are used inside the components but will be assigned/re-mapped to component specific variable names. For example, the variable `@color-shopware-blue` could be used for a border color or a background color inside a component.
- As a result, the colors and other styling can be adjusted for each component individually.
- The component specitif variables are declared at the top of the components LESS file.
- They should begin with the component name like `@sw-button-color-background`.

**Button component example:**

```
@sw-button-primary-color-background: @color-shopware-blue;
@sw-button-primary-color-text:       @color-iron;
@sw-button-border-radius:            @border-radius-default;

.sw-button {
  border-radius: @sw-button-border-radius;
  
  &.sw-button--primary {
    color: @sw-button-primary-color-text;
    background-color: @sw-button-primary-color-background;
  }  
}
```

## LESS structure for nested components

Sometimes a component needs different styling when it is nested inside a parent component. In this case the parent 
component is defining the LESS for the child component.

**Nested components example:**

```
.sw-card {
    border: 1px solid @sw-card-color-border;
    padding: 40px;
    
    .sw-tabs {
        // Special stying when sw-tabs item is inside a card
    }
}
```

## Component Twig blocks

- The core components contain twig blocks to provide the possibility to extend or override the components.
- The root block wraps the component and has the component name: `{% block sw_component %}`
- All block names of a component have the component name as a prefix.
- The `<slot>` element has an inner block: `{% block sw_component_slot_default %}`
- If there are multiple slots, they should be named after the slot name: `{% block sw_component_slot_message %}`

**Component Twig blocks example:**

```
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