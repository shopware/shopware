# Create a component
Components are the fundamental part of the Shopware administration. Literally everything is a component. Shopware already provides a bunch of useful components. These can also be extended or overridden. Shopware has a thin layer architecture which provides interfaces to register new components or to extend and override existing components. More on the thin layer and the functionality it provides later on.

## Creating a new component
In the next couple of steps you'll learn how to create a component, how the file structure needs to be, add your logic to the component as well as how to register the component in the system.
 
### File structure
For a component you have to create a new folder with some files. Separation of concerns is important, even more for complex components, the styles, component definition and template code are splitted in separate files. The `index.js` file is the main entry file for a component. It contains the logic of the component as well as imports the other necessary files. Next there is the `.hmtl.twig` file which contains the components HTML template. The `.less` file contains all of the styling. The file structure of a component could look like this:
```
─── sw-hello-world
    ├── index.js
    ├── sw-hello-world.html.twig
    └── sw-hello-world.less

```

At least the `index.js` is required. The template and less file are only necessary if there is content for them.

### Component JavaScript
```js
// sw-hello-world/index.js

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
*Register new component using the third-party interface*

In the code example above, the new component is registered in the application. In order to do so the `Component` object has to be imported from the third-party interface. The interface takes care of delivering the component to the application and thereby registering it as a Vue.js component. The template file is also imported and used in the newly created component. The less file gets automatically compiled in the building process when it is imported.

### Template
It is possible to use inline templating for the component for example with [JavaScript template literals](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Template_literals), but for a better overview, especially in more complex components a new file for the template should be created:

```twig
// sw-hello-world/sw-hello-world.html.twig

{% block sw_hello_world %}
    <div class="sw-hello-world">
        {% block sw_hello_world_headline %}
            <h1>Hello world!</h1>
        {% endblock %}
    </div>
{% endblock %}
```
*Create template with root element*  

Since the [Twig.js templating engine](https://github.com/twigjs/twig.js/wiki) is used for extending functionalities template blocks can be defined. Later on they can be overridden or extended by third-party developers or components. More information on this can be found in the Extending / overriding section **LINK TO EXTENDING/OVERRIDING** . More information on this can be found in the Extending / Overriding section of this guide.
Note that Vue.js requires **exactly one** component "root" element. As a convention a `<div>` element is utilized carrying the component name as its class name.

### Styling
LESS is used for styling the component. LESS is a so called CSS preprocessor which provides options such as variables, nesting of selectors, mixins, inline media queries and much more. To learn more about LESS and it's functionalities, there is an [in-depth guide](http://lesscss.org/features/) on the [project homepage](http://lesscss.org/).

In your components folder create a new LESS-file named after the component name, for this example this would be `sw-hello-world.less`.

Import our global variables to the newly created file in order to achieve a consistent look for your component:  

```less
// sw-hello-world/sw-hello-world.less

@import "~less/variables";

.sw-hello-world {
    // ...
}
```
*Add less file for styling*

Make sure to take a look at our naming convention in this guide **LINK TO BEM**.

## Adding functionalities
The component is up and running now and you'll enhance the functionality of the component in the following chapter. The WebSpeech API is utilized to speak out loud the text the user entered into the input field of the component.

### Data binding and events
As an example, the component `sw-hello-world` should provide an input field for entering text and a button that triggers the [WebSpeech API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API) making it read out loud the text entered beforehand.

First extend the template file:  

```vue
// sw-hello-world/sw-hello-world.html.twig

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
*Adding input fields to our template*

Vue.js' [event binding](https://vuejs.org/v2/guide/events.html) and [two-way data binding](https://vuejs.org/v2/guide/forms.html) are used. With these changes in place, you can implement the basic state of the component and the method `onSayText` representing an event handler method. It is triggered everytime the user clicks the button.

```js
// sw-hello-world/index.js

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
*Adding data variables und methods to the component*

### List rendering and event binding
To extend the components logic and functionalities even further, an option to select the kind of voice will be added.  

```js
// sw-hello-world/index.js

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
*Add further component logic*

In the code displayed above, a little workaround for the [WebSpeech API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API) is needed.


```html
// sw-hello-world/sw-hello-world.html.twig

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
*Add select field for the kind of speech*

The code example above uses some Vue.js' functionalities like [list rendering](https://vuejs.org/v2/guide/list.html), [attributes interpolation rendering](https://vuejs.org/v2/guide/syntax.html#Attributes) or [event binding](https://vuejs.org/v2/guide/events.html) within the template.

## Localization
The third-party interface allows to extend the registered locales (default `en-GB` and `de-DE`) and add custom interface translations. The system is based on the [`vue-i18n` plugin](https://kazupon.github.io/vue-i18n/en/). The plugin supports pluralization, placeholders in snippets, HTML formatting, as well as date and number formatting.

The next example adds some english translations for the component.

```js
// sw-hello-world/index.js

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
*Add translations*

The `vue-i18n` plugin populates the methods `$t()` for basic translations with placeholders, `$tc()` for pluralization, `$d()` for date formatting and `$n()` for number formatting. When an interface translation is not defined in the current locale, the fall-back option is `en-GB`.

Let's enhance the component template with own interface translations:  

```vue
// sw-hello-world/sw-hello-world.html.twig

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
*Use of translations in the template*

It is also possible to register new locales with the third-party interface.
```js
// sw-hello-world/index.js

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
*Register a new locale*

Separation of concerns is also possible here. To do so, place your interface translations into a JSON file, import it and use it instead of the object we provided in the previous example:


```js
// sw-hello-world/index.js

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
*Import translations from file*

The snippet file could look like this:  

```json
// sw-hello-world/snippets/es-ES.json
{
    "global": {
        "sw-hello-world": {
            "placeholderField": "Ingrese su texto...",
            "buttonSayText": "Decir {msg}!"
        }
    }
}
```
*Translation file*

## Extending components
Existing components can be easily extended. The application provides a system of multiple inheritances for the component logic, as well as the component template.

In the following example component extension is used. The new component `sw-hello-foo` extends from the previously created `sw-hello-world` example and changes some functionalities.

The file structure is the same as for creating a new component:
```
└── sw-hello-foo
    ├── index.js
    ├── sw-hello-foo.html.twig
    └── sw-hello-foo.less
```

```js
// sw-hello-foo/index.js

import { Component } from 'src/core/shopware';
import template from './sw-hello-foo.html.twig';

Component.extend('sw-hello-foo', 'sw-hello-world', {
    template,

    methods: {
        alertElement() {
            console.log('I can be included in templates by using a <sw-hello-foo> Element');
        }
    }
});
```
*Extend a component*

The template file `sw-hello-foo.html.twig` only contains the Twig blocks we want to modify. The rest of the template will be derived from the extended component.

```twig
// sw-hello-foo/sw-hello-foo.html.twig

{% block sw_hello_world_headline %}
    <h1>Foo content before</h1>

    <button @click="alertElement">
        Alert
    </button>

    {% parent %}
{% endblock %}
```
*Using twig block system for extending*

The `{% parent %}` call renders the content of the parent template. Please note that shopware provides an own implementation of the `parent` call to support multiple inheritances.

Stylings can be added or overidden using the less file.
```less
// sw-hello-foo/sw-hello-foo.less
@import "~less/variables";

.sw-hello-world {
    // ...
}
```
*Add or override stylings when extending a component*

## Overriding components
It is also possible to override a component. Here's a small example:

```js
// sw-hello-bar/index.js

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
*Overriding a component*

## Calling a method from an overridden / extended component
When you are overriding or extending a component, you may want to call the original implementation of the method logic and add your own logic on top of it. For this purpose the property `$super` is added to the Vue component instance.

```js
// sw-hello-bar/index.js

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
*Calling a function of an overriden component using `$super`*

The `$super` property works for both - extended and overridden components and provides the same functionality for both cases.

## Dependency management
[Vue.js' dependency management](https://vuejs.org/v2/api/#provide-inject) is used with `provide` and `inject` properties to provide our service providers. To use a service provider like the login or main menu service, you simply use the `inject` property.

```js
// sw-hello-world/index.js

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
*Inject a service*

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

These services are so called API services. These are an abstraction layer of the REST API. A couple specialized services for certain tasks are provided as well:

* `menuService`
* `jsonApiParserService`
* `loginService`
* `validationService`

### Register own service
It is also possible to register your own service provider using the third-party interface.  

```js
// example.js

Shopware.Application.addServiceProvider('exampleService', () => {
    return new ExampleService();
});
```
*Adding a service provider*

The example service can then be injected the same way:

```js
// sw-hello-world/index.js

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
*Injecting the example service*

## Mixins
Mixins are used to inject additional functionality to a component. Therefore you can use the [Vue.js' mixins property](https://vuejs.org/v2/guide/mixins.html).

```js
// sw-hello-world/index.js

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
*Using a mixin*

The following mixins are available:

* `listing`
* `validation`
* `notification`
* `drag-selector`

### Creating new mixin
As a third-party developer you can create your own mixin for reusable component logic.

```js
// example.js

import { Mixin } from 'src/core/shopware';

Mixin.register('exampleMixin', {
    // ...
});
```
*Registering a new mixin*
