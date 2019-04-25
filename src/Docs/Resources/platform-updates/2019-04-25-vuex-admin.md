[titleEn]: <>(Vue Vuex in the Administration)

Vuex is a Vue.js plugin and library that allows you to share state between components. This is done by using a single object which is accessible by all component via the `$store` property. Vuex applies a flux pattern on this store which means every change of the store's state must be done via commits (synchronous changes) or actions (asynchronous changes).

Well this is not completely true because we deactivated strict mode (more on this later).

This is not a documentation about Vuex but an overview how we want to use Vuex in our application. If you are not familiar with Vuex I strongly recommend reading the documentation at [vuex.vuejs.org](vuex.vuejs.org).

### Define Own Store Modules

Store modules should only be registered by the top-level components of complex structures (e.g. your `sw-page` component or things like the `sw-component-tree`). Keep in mind that the preferred way to share state in Vue.js still is passing properties to children and emitting events back.

We recommend to create your module in a separate Javascript file in your components folder named `state.js` that exports your state definition

```javascript
// state.js
export default {
   namespaced: true,
   state: { },
   mutations: { ... }
}
```

To register the module use the `registerModule` function of the Vuex store in the `beforeCreated` lifecycle hook of your component. Also don't forget to clean up your state when your component is destroyed.

If you register a module on the store keep in mind that it follows the same rules as if you would create a component. That means that store modules which are created from the same source share a static state. if you need a "clean" store module every time you register it and (in most cases this is exactly what you want) define your state property as a function. see https://vuex.vuejs.org/guide/modules.html#module-reuse for an explanation

```javascript
export default {
  state() {
	  return { ... };
  }  
```

As convention your store module name should be your component's name in camelcase (because you must be able to access the name in object notation).

```javascript
import componentNameState from './state.js'

export default {
  name: 'component-name'

  beforeCreated() {
    this.$store.registerModule('componentName', componentNameState);
	}
  beforeDestroye() {
    this.$store.unregisterModule('componentName');
	}
```

You may note that we don't follow our usual convention to wrap the functionality of the lifecyclehook in an extra method. This is because the registration of your state is mandatory and should not be overwritten by components extending your component.

### Strict mode and Problems with v-model

Because Vuex does not work well with Vue.js' `v-model` directive we turned off strict mode. That means that state can be written directly. However, avoid changing the state directly as much as possible because it could cause problems with Vue.js' reactivity. At least first level properties of your module must be commited.

```javascript
// state.js
export default {
   state: {
    // product is a first level property
    product {
      // id may be changed directly with full reactivity
      id: ''
    }
   },
   mutations: { ... }
}
```

### Global State

Right now we're migration global state to vuex stores. This includes the current language and admin locale as well as notification management and error handling. All global actions and mutations will be documented in the component library eventually.

If you need to create global state on your own you can create an Vuex module in the `/src/app/state/` folder of the application. Because the Vuex modules must be named we could not apply automatic registration (yet). So You must add your global module manually in `/src/app/state/index.js` .
