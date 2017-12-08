# Shopware Backend components state tree

## Usage
Register your own state tree using the method `Shopware.State.register`, see the full documentation on how to create a state
tree including actions, getters and mutations in the [official documentation](https://vuex.vuejs.org/en/core-concepts.html).

## Defining a state tree module
We're providing you with a convenience function which registers your state tree module in the application.

```
Shopware.State.register('product', {
    state() {
        return {
            /** ... */
        };
    },
    actions: { /** ... */ },
    mutations: { /** ... */ }
})
```

Please make sure the `state` property is a function returning an object with the initial state of the module.

## Using the state tree in components
All registered state tree modules are namespaced, therefore they're standing on their own. To use them in your component you
simply define an object in your component definition:

```
Shopware.Component.register('sw-login', {
    stateMapping: {
        state: 'login'
    }
});
```

In the background we're generating getters and setters for you (see for [further information](https://vuex.vuejs.org/en/forms.html#two-way-computed-property)).
This enables you to use `v-model` on your template without worrying about necessary computed properties including their 
getters and setters.

You can customize the behavior when defining a property called `properties` to define the state properties you want to use in 
your component.

```
Shopware.Component.register('sw-login', {
    stateMapping: {
        state: 'login',
        properties: ['isWorking', 'username', 'password']
    }
});
```

If you don't want any properties from the state tree and deal with it on your own you reach this goal with the following:

```
Shopware.Component.register('sw-login', {
    stateMapping: {
        state: 'login',
        properties: false
    }
});
```

Actions are playing a big role in `VueX`, they're enabling you to communicate with the Backend API and provides an way to
implement your business logic.

To make your life easier we're automatically mapping the available actions of the state tree module. You can change this
behavior as well.

```
Shopware.Component.register('sw-login', {
    stateMapping: {
        state: 'login',
        actions: ['loginUserWithPassword']
    }
});
```

If you don't want to use map any actions to your component and dispatch the actions. You can either provide `false` or an 
empty array:

```
Shopware.Component.register('sw-login', {
    stateMapping: {
        state: 'login',
        actions: false
    }
});
```