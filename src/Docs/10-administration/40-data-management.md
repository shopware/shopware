[wikiUrl]: <>(../administration/data-management)

# Data management
Data is what makes the world go round - even more in web applications like the administration. This guide features an 
in-depth look on the data management, the basic concepts behind it and how to use it in components and services.

## Concept
The entity proxy is representing an entity in the system. Entity proxies are used in combination with an entity store
 which is basically a collection of items including convenience functions to load a certain set of data as well as 
 saving, updating and deleting entities using the REST API endpoint.

Stores are using custom API services which are available throughout the application to provide an easy-to-use way to 
fetch and send data to the REST API using CRUD operations.

### Scheme definition
To prevent the tedious process of providing all possible fields to the entity proxy the administration is automatically 
generating entities using an entity scheme from the REST API. The entity scheme is a collection of all entities 
registered in the system and includes information about the field, their data type and associations to one other. The 
stores in the application are generated automatically based on the scheme as well.

The benefit of this approach is that the data management in the application is completely dynamic. New fields and 
entities will be added to the entity scheme automatically and are therefore available in the administration right away. 
New fields and entities which are added by a plugin will be added to the entity scheme, too.

### Changeset generation
The `EntityProxy` generates a draft and original data object out of the initial data. All further changes to the entity 
will be applied to the draft. It enables the administration to generate changesets to send only the changed fields, 
unsaved changes can be rolled back on-the-fly and changes can be queued.

## Working with data
The third-party developer interface of the administration provides a module called `State` which represents an entity 
store manager. Using the method `getStore()` you can easily get a certain store in your components as well as other 
services. 

### Using a store in your component

In the following example we're getting the `product` store from the entity store manager and using the provided 
`getList()` to get a paginated list from the REST API implemented in a example component:

```
import { Component, State } from 'src/core/shopware';
import template from './sw-hello-world.html.twig';

Component.register('sw-hello-world', {
    template,
    
    data() {
        return {
            title: 'Hello World'
        };
    },
    
    computed: {
        productStore() {
            return State.getStore('product');
        }
    }
});
```
*Adding and using a store in a component*

## Fetching data
After adding a `computed` property which provides the store to the component, it's possible to request data right 
away. The store provides the following methods to fetch data:

* `getById()`
    * Fetches a certain record using an unique identifier from the REST API. If the store contains a record with the 
    provided id, the store will return the model right away without requesting it from the REST API.
* `getByIdAsync()`
    * As `getById()` the method fetches a certain record using an unique identifier from the REST API. Unlike 
    `getById()` it always fetches the item from the REST API using the specific API service.
* `getList()`
    * Fetches a paginated list from the REST API endpoint.
 
### Fetching a paginated list

```
import { Component, State } from 'src/core/shopware';
import template from './sw-hello-world.html.twig';

Component.register('sw-hello-world', {
    template,
    
    data() {
        return {
            title: 'Hello World',
            isLoading: false,
            products: []
        };
    },
    
    computed: {
        productStore() {
            return State.getStore('product');
        }
    },
    
    created() {
        this.isLoading = true;
        this.products = [];
        
        this.productStore.getList({
            limit: 25, // Possible values: 1, 5, 10, 25, 50, 75, 100, 500,
            page: 1
        }).then((products) => {
            this.products = product;
            this.isLoading = false;
        });
    }
});
```
*Fetching a paginated list of products*

The `getList()` method of the store can be combined with the `listing` mixin which provides an convenient way to enable 
pagination in your module / component. The mixin assumes that a method called `getList()` exists in your component. It 
automatically applies the request parameters from the store to the URL e.g. `index?limit=25&page=3`:

```
import { Component, State, Mixin } from 'src/core/shopware';

Component.register('sw-hello-world', {
    template,
    
    mixins: [
        Mixin.getByName('listing')
    ],
    
    computed: {
        productStore() {
            return State.getStore('product');
        }
    },
    
    data() {
        return {
            isLoading: false,
            products: []
        };
    },
    
    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();
            
            this.products = [];
            return this.productStore.getList(params).then((response) => {
                this.total = response.total;
                this.products = response.items;
                this.isLoading = false;
                
                return this.products;
            });
        }
    }
});
```
*Using the "listing" mixin in a component*

Using the `listing` mixin is a quite common pattern for list views used throughout the administration. The mixin 
provides all information needed for the `sw-pagination` and `sw-grid` component:

```
<sw-grid :items="products" :sortBy="sortBy" :sortDirection="sortDirection">

    <!-- Grid columns definition -->
    <template slot="columns" slot-scope="{ item }">
        <sw-grid-column dataIndex="name" flex="1fr" sortable truncate>
            {{ item.name }}
        </sw-grid-column>
    </template>
    
    <!-- Pagination -->
    <sw-pagination
        slot="pagination"
        :page="page"
        :limit="limit"
        :total="total"
        :total-visible="7"
        @page-change="onPageChange">
    </sw-pagination>
</sw-grid>
```
*Generic listing template*

The `sw-pagination` component needs another method available in the component called `onPageChange`. The method will 
be called when the event `page-change` got fired which happens when the user clicks on of the page numbers. Due to the 
`listing` mixin the method should be part of your component already.

### Fetching a single item

The store provides two methods to fetch a single record - `getById()` and `getByIdAsync()`. The main difference is that 
`getById()` does a lookup on the store and returns the entity when it was found locally. `getByIdAsync` on the other 
hand always fetches the entity from the REST API endpoint. The component gets the ID of the entity from the 
URL e.g. `#/product/detail/<product-uuid>`.

```
import { Component, State, Mixin } from 'src/core/shopware';

Component.register('sw-hello-world', {
    data() {
        return {
            product: {}
        };
    },
    
    computed: {
        productStore() {
            return State.getStore('product');
        }
    },
    
    created() {
        const productId = this.$route.params.id;
        
        // Getting the product information
        this.product = this.productStore.getById(productId);
    }
});
```
*Fetching a product from the REST API*

Both methods are populating the entitiy with an empty data set to provide the necessary object structure for the 
Vue.js template and fetches the entity from the REST API end point in the background. Due to Vue.js' data binding the 
information in the component (in this case `this.product`) will automatically updated when the application receives the 
information from the API. `getByIdAsync()` always returns a promise and returns the fetched information in the 
`then()` call:

```
import { Component, State, Mixin } from 'src/core/shopware';

Component.register('sw-hello-world', {
    data() {
        return {
            isLoading: false,
            product: {}
        };
    },
    
    computed: {
        productStore() {
            return State.getStore('product');
        }
    },
    
    created() {
        const productId = this.$route.params.id;
        
        // Getting the product information
        this.isLoading = true;
        this.productStore.getByIdAsync(productId).then((product) => {
            this.product = product;
            this.isLoading = false;
        });
    }
});
```
*Fetching a product from the REST API using `getByIdAsync()`*

Vue.js reuses components when just a part of the URL change occurs. In this case the component will not re-run the 
`created` method. To solve this issue a watcher can be added to the component which detects URL changes, so every 
time the ID in the URL changes, the new information can be fetched from the API.

```
import { Component, State } from 'src/core/shopware';

Component.register('sw-hello-world', {
    data() {
        return {
            product: {}
        };
    },
    
    computed: {
        productStore() {
            return State.getStore('product');
        }
    },
    
    created() {
        this.createdComponent();
    },
    
    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },
    
    methods: {
        createdComponent() {
            const productId = this.$route.params.id;
            
            // Getting the product information
            this.product = this.productStore.getById(productId);
        }
    }
});
```
*Fetching an entity from the REST APi when the URL parameter "id" gets changed*

### Filter, search, limit & sorting
The REST API request can accept additional parameters and provides the abilities to search, filter, limit and sort the 
result set returned from the API request. Those parameters can be applied to any API request, even when they're not 
using a store to fetch data from the API.

#### Searching
Searching is as easy as providing an additional parameter to the `getList()` method additionally to the `page` and 
`limit` parameters.

```
createdComponent() {
    this.isLoading = true;
    
    // Getting the product information
    this.productStore.getList({
        limit: 25,
        page: 1,
        term: 'Search term'
    }).then((products) => {
        this.products = products;
    });
}
```
*Searching using the `getList()` method*

#### Limiting & sorting
Additionally to the search term it's possible to filter and sort the result of the API request. The `getList()` accepts 
the addititonal parameters `sortDirection` and `sortBy`. As a sort direction you can choose `ASC` (ascending) or 
`DESC` (descending). The `sortBy` needs to be prefixed with the entity name followed by the field you want to use for 
the sorting. For example filtering on the product name the `sortBy` parameter would be `product.name`. The `listing` 
mixin supports sorting, limiting and searching out-of-the-box.

```
createdComponent() {
    this.isLoading = true;
    
    // Getting the product information
    this.productStore.getList({
        limit: 25,
        page: 1,
        sortBy: 'product.name',
        sortDirection: 'ASC'
    }).then((products) => {
        this.products = products;
        
        this.isLoading = false;
    });
}
```
*Sorting the result using the `getList` method*


#### Filtering
The REST API supports sophisticated filtering using nested critierias. The `CritieriaFactory` provides an easy-to-use 
interface to build criterias. The factory provides the following methods:

* `multi()`
    * Creates a multi query. A multi query can either be used with an `AND` operator or `OR` operator.
* `contains()`
    * Creates a match criteria query
* `equals()`
    * Creates a new term query. If an array of values is provided as the second argument we automatically creating 
    a terms query instead of a term query.
* `equalsAny `
     * Creates a terms query. It's quite similar to a term query with the difference that it accepts multiple values 
     for one field.
 * `not()`
    * Creates a not query which is useful for excluding queries. A not query can be combined with a multi query as well.
* `range()`
    * Creates a range query. Useful for price filtering for example.

```
import CriteriaFactory from 'src/core/factory/criteria.factory';

// ...

CriteriaFactory.multi(
    'AND',
    CriteriaFactory.equals('product.name', 'example'),
    CriteriaFactory.equalsAny('product.name', ['shopware', 'shopware AG']),
    CriteriaFactory.range('product.age', {
        '>': 10,
        '>=': 9,
        '<': 20,
        '<=': 19
    ),
    CriteriaFactory.not(
        'OR'
        CriteriaFactory.equals('product.name', 'another example'),
        CriteriaFactory.equalsAny('product.name', ['example manufacturer', 'another manufacturer'])
    ),
    CriteriaFactory.multi(
        'AND',
        CriteriaFactory.multi(
            'AND',
            CriteriaFactory.range('product.age', {
                '>': 10
            }),
            CriteriaFactory.equals('product.manufacturer', 'yet another manufacturer')
        ),
        CriteriaFactory.multi(
            'AND',
            CriteriaFactory.range('product.age', {
                '<': 50
            }),
            CriteriaFactory.equals('product.manufacturer', 'example manufacturer')
        )
    )
)
```
*CriteriaFactory combination example*

Each method of the `CriteriaFactory` returns an output interface to interact with the queries. The interface provides 
the following methods:

* `getQuery()`
    * Returns the query as a plain object. 
* `getQueryString()`
    * Returns a JSON stringified version of the query.

The resulting query combined with the `getQuery()` method of the output interface from the Criteria factory can be 
used in conclusion with the `getList()` method of the stores to filter the result. The `getList()` method accepts the 
additional parameter `criteria` and will call the `getQuery()` method of the output interface. The API service in the
background takes care of formatting the query in the way the REST API needs it and reformat the response the way the
administration excepts it.

```
import CriteriaFactory from 'src/core/factory/criteria.factory';

// ...

createdComponent() {
    this.isLoading = true;
    
    const criteria = CriteriaFactory.multi(
        'AND',
        CriteriaFactory.range('product.price.net', {
            '<': 50
        }),
        CriteriaFactory.equals('product.manufacturer.name', 'shopware AG')
    );
    
    // Getting the product information
    this.productStore.getList({
        limit: 25,
        page: 1,
        sortBy: 'product.name',
        sortDirection: 'ASC', 
        criteria: criteria
    }).then((products) => {
        this.products = products;
        
        this.isLoading = false;
    });
}
```
*Using a query for filtering using the `getList()` method of a store*

### Updating a record
Vue.js two-way data binding using the `v-model` directivemakes it super easy and convenient to update entities. After 
fetching the record it is as easy as using the `sw-field` and `sw-text-editor` components to create a form which 
updates the record:

```
<sw-card title="Basic product information">
    <sw-field type="text" label="Product name" v-model="product.name"></sw-field>
    
    <sw-field type="boolean" label="Activate product" v-model="product.active"></sw-field>
    
    <sw-button @click="onSaveProduct">Save changes</sw-button>
</sw-card>
```
*Updating an entity using Vue.js' `v-model` directive in the template*

Notice an event handler called `onSaveProduct` has been added to the `sw-button` component which will be fired when the 
user clicks the button. The event handler provides the additional logic to save the updated entity.

```
import { Component, State, Mixin } from 'src/core/shopware';

Component.register('sw-hello-world', {
    data() {
        return {
            product: {}
        };
    },
    
    computed: {
        productStore() {
            return State.getStore('product');
        }
    },
    
    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },
    
    created() {
        this.createdComponent();
    },
    
    methods: {
        createdComponent() {
            const productId = this.$route.params.id;
        
            // Getting the product information
            this.product = this.productStore.getById(productId);
        },
        
        onSaveProduct() {
            this.product.save().then((product) => {
                this.product = product;
                // Show a success notification to the user...
            });
        }
    }
});
```
*Updating an entity using a form*

Updating multiple entities is as simple as saving a single entity. The store provides a method called `sync` which will 
sync all changes (new entities, updated entities and deleted entities) using the API services to the server. New, 
updated and deleted in association stores will be synced too.

```
computed: {
    productStore() {
        return State.getStore('product');
    }
},

// ...
    
onSaveProduct() {
    this.productStore.sync().then((response) => {
        console.log(response);
        // Show a success notification to the user...
    });
}
```
*Using the `sync()` method of the store*

### Creating a new entity
Creating new entities is as easy as updating an existing one. The store provides a handy method called `create()` which 
will create new entities on the go. The method will automatically provide an Uuid v4 for the newly created entity. 
While creating the entities based on the entity scheme, we'll automatically generate association stores provided by the
information from the entity scheme.

```
Component.register('sw-hello-world', {
    data() {
        return {
            product: {}
        };
    },
    
    computed: {
        productStore() {
            return State.getStore('product');
        }
    },
    
    created() {
        this.product = this.productStore.create();
    },
    
    methods: {
        onSaveProduct() {
            this.product.save().then((product) => {
                this.product = product;
                // Show a success notification to the user...
            });
        }
    }
});
```
*Using the `create()` method of the store in a component*

### Deleting an item
The entity proxy provides the method `delete()` which enables you to delete an item. The method provides two ways. 
Using the default behaviour the entity will be marked as deleted in the store and will be deleted when the `sync` 
method of the store will be called. If you provide the parameter `directDelete` the entity will be deleted right away.

```
Component.register('sw-hello-world', {
    data() {
        return {
            product: {}
        };
    },
    
    computed: {
        productStore() {
            return State.getStore('product');
        }
    },
    
    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },
    
    created() {
        this.createdComponent();
    },
    
    methods: {
        createdComponent() {
            const productId = this.$route.params.id;
    
            // Getting the product information
            this.product = this.productStore.getById(productId);
        },
        onSaveProduct() {
            this.product.save().then((product) => {
                this.product = product;
                // Show a successful save notification to the user...
            });
        },
        
        onDeleteProduct() {
            this.product.delete().then(() => {
                // Show a successful delete notification to the user...
            });
        }
    }
});
```

*Deleting an item from the detail page*

The store provides the methods `remove()`, `removeById()` and `removeAll()` which are providing the ability to remove 
entities from the store. Unlike the `remove()` method of the entity the store just removes the item from the 
collection but does not delete it using an DELETE request.

### The `EntityStore` and `EntityProxy` in detail
The entity store is a collection of entity proxies. The store uses an API service to communicate with the REST API. The 
API returns responses in the JSON API format. The API service takes care of reformatting the response using a JSON API 
parser. The store on the other hand provides a bunch of convenient features. Here's an overview of all methods, we 
haven't covered before:

* `duplicate()`
    * Duplicates an entry in the store and returns the newly duplicated one. 
*  `add()`
    * Adds an existing entity to the store.
* `remove()`
    * Removes an existing entity from the store.
* `removeById()`
    * Removes an existing entity using the ID of the entity from the store.
* `removeAll()`
    * Removes all existing entities from the store.

The entity proxy on the other hand is the representation of an item in the system. It provides the ability to generate 
changesets, populates itself when data got fetched and provides basic validation of the data inside the item using 
the entity scheme.

* `setData()`
    * Overrides the original & draft of the entity and discards the changes with provided data.
* `setLocalData()`
    * Sets the draft of the entity with the provided data.
* `discardChanges()`
    * Discards the local changes to the entity and resets it to the original state.
* `applyChanges()`
    * Applies the draft changes to the original. If the entity was not saved before calling this method, the changeset 
    generation can't detect changes anymore.
* `delete()`
    * Marks the entity as deleted in the associated store. It's possible to delete the entity right away.
* `remove()`
    * Removes the entity locally from the store but don't deletes the entity using the REST API.
* `validate()`
    * Validates the entity against the entity scheme and checks if all required fields are filled.

### Associations
Unlike 1-1 associations, 1-n associations are not loaded by default when fetching a list or single item from the API 
due to performance reasons. Those associations have to be fetched in a separate call as a paginated list 
(`limit` and `page` needs to be provided to the call). The entity scheme provides the relationship between entities in 
the system and automatically creates assocation stores from them. These stores are available on an entity using 
the `getAssocation()` method.

```
import { Component, State } from 'src/core/shopware';

Component.register('sw-hello-world', {
    data() {
        return {
            product: {}
        };
    },
    
    computed: {
        productStore() {
            return State.getStore('product');
        }
    },
    
    created() {
        this.createdComponent();
    },
    
    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },
    
    methods: {
        createdComponent() {
            const productId = this.$route.params.id;
            
            // Getting the product information
            this.product = this.productStore.getById(productId);
            
            // Fetching the categories of a product
            this.product.getAssociation('categories').getList({
                limit: 25,
                page: 1
            });
        }
    }
});
```
*Fetching an association using an entity*

The method `getAssociation()` populates the parent entity with the information when the data got fetched successfully. 
Due to Vue.js' built-in data binding the information will be automatically updated in the component as well.

### Naming of API services and entities
Due to the fact we are generating the API services, entity stores and association stores based on the entity scheme the
naming of those components differs from each other. In the following is an overview of the naming scheme:

* API service
    * API routes are defined with a hyphen. For example the API route for the media folder will be defined as `media-folder`
* Entity stores
    * Entity stores are defined with an underscore as a separator. For example `Shopware.State.getStore('media_folder');`
* Association stores
    * Associations stores on the other hand are using camel case for the name of the association store. For example `this.media.getAssociation('mediaFolder')`
