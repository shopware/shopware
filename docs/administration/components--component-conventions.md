# Component conventions

## Table of contents

1. <a href="#components">Component conventions</a>
   - 1.1 <a href="#components-general">General</a>
   - 1.2 <a href="#components-props-order">Component properties order</a>
   - 1.3 <a href="#components-lifecycle-hooks">Lifecycle hooks</a>
   - 1.4 <a href="#components-variants">Component variants</a>
2. <a href="#markup-less">Markup and LESS</a>
   - 2.1 <a href="#markup-bem">BEM</a>
   - 2.2 <a href="#markup-less-variables">LESS variables</a>
   - 2.3 <a href="#markup-nested">LESS structure for nested components</a> 
   - 2.4 <a href="#markup-twig">Component Twig blocks</a>
   
## 1. <span id="components">Component conventions</span>

### 1.1 <span id="components-general">General</span>

 - Each component should be as slim as possible.
 - The component stands for its own and fulfills only one purpose. It is better to make multiple smaller components than putting too much complexity into a single component.
 - The component contains not much logic. Complex logic is handled by the modules.
 - The component is documented inside the storybook.
   
### 1.2 <span id="components-props-order">Component properties order</span>

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

**Component properties example:**

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

### 1.3 <span id="components-lifecycle-hooks">Lifecycle hooks</span>

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

### 1.4 <span id="components-variants">Component variants</span>
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


## 2. <span id="markup-less">Markup and LESS</span>

### 2.1 <span id="markup-bem">BEM</span>

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

### 2.2 <span id="markup-less-variables">LESS variables</span>

#### General variable naming convention

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

#### Global variables

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

### 2.3 <span id="markup-nested">LESS structure for nested components</span>

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

### 2.4 <span id="markup-twig">Component Twig blocks</span>

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