[titleEn]: <>(Api error handling in administration)

We reorganized the way we store errors that are received from the api. 
 
We figured out that the binding between an api error and its input field's `v-model` expression is not practicable due to naming conflicts in collections.
In addition, not every input field is bound to entity data or can/should receive an api error.
That's why we removed the `error-pointer` property and the `resetFormError` method from fields and replaced it with an `error` property.

We also decoupled nested association errors to have a flatter store which looks like:
```
(state)
 |- entityNameA
    |- id1
        |- property1
        |- property2
        ...
    |- id2
        |- property1
        |- property2
        ...
 |- entityNameB
   ...         
```

### Read errors from the store

Errors can be read from the store by calling the getter method `getApiErrorFromPath`.

```
function getApiErrorFromPath (state) => (entityName, id, path)
```

Where path is an array representing the nested property names of your entity.
Also we provide a wrapper which can also handle nested fields in object notation 

```
function getApiError(state) => (entity, field)
```

which is much easier to use for scalar fields.
In your Vue component, use computed properties to not flood your templates with store calls.

````
<script>
    computed: {
        propertyError() {
            return this.$store.getters.getApiError(myEntity, 'myFieldName');
        }
        nestedpropertyError() {
            return this.$store.getters.getApiError(myEntity, 'myFieldName.nested');
        }
    }
</script>

<template>
    <sw-field ... :error="propertyError"></sw-field>
</template>
````

### mapErrors Service

Like every Vuex mapping, fetching the errors from the store may be very repetitive and error-prone.
Because of this we provide you an Vuex like mapper function


```
mapApiErrors(subject, properties)
```

where subject is the variable name (not the entity itself) and properties is an array of properties you want to map.
You can spread its result to create computed properties in your component.
The functions returned by the mapper are named like a camelcase representation of your input suffixed with `Error`.  
This is an example from the `sw-product-basic-form` component:

```
<script>
    import { mapApiErrors } from 'src/app/service/map-errors.service';
    
    Component.register('sw-product-basic-form', {
        
        computed: {
            ...mapApiErrors('product', ['name', 'active', 'description', 'productNumber', 'manufacturerId', 'tags']),
        }
</sript>

<template>
    <sw-field type="text" v-model="product.name" :error="productNameError"
</template>

``` 

### Error configuration for pages

When working with nested views you need a way to tell the user that an error occurred on another view, e.g tab.
For this you can write a config for your `sw-page` component which (currently) looks like: 

```
{
    "nested.route.name": {
        "entityVariable": [ prop1, prop2 ...]
    }
}
```

We provide the `mapPageErrors(errorConfig)` mapper function to create computed properties from it.