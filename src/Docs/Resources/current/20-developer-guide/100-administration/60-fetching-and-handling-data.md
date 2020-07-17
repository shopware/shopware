[titleEn]: <>(Fetching and Handling Data)
[hash]: <>(article:developer_administration_fetching_and_handling_data)

The data handling was created with **predictability** as its main design goal. It uses a *repository pattern* which is strongly based on the [Database Abstraction Layer](./../../60-references-internals/10-core/130-dal.md) from the core.

## Relevant classes

`Repository`
 : Allows to send requests to the server - used for all CRUD operations

`Entity`
 : Object for a single storage record

`EntityCollection`
 : Enable object-oriented access to a collection of entities

`SearchResult`
 : Contains all information available through a search request

`RepositoryFactory`
 : Allows to create a repository for an entity

`Context`
 : Contains the global state of the administration (Language, Version, Auth, ...)

`Criteria`
 : Contains all information for a search request (filter, sorting, pagination, ...)

## Get access to a repository

To create a repository it is required to inject the RepositoryFactory:

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        this.repository = this.repositoryFactory.create('product');
    }
});
```

You can also change some options in the third parameter:

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        const options = {
            version: 1, // default is the latest api version
            compatibility: true // default is true
        };

        this.repository = this.repositoryFactory.create('product', null, options);
    }
});
```

## How to fetch listings

To fetch data from the server, the repository has a `search` function. Each repository function requires the api `context`. This can be get from the Shopware object:

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        // create a repository for the `product` entity
        this.repository = this.repositoryFactory.create('product');
        
        this.repository
            .search(new Criteria(), Shopware.Context.api)
            .then((result) => {
                this.result = result;
            });
    }
});
```

## Working with the criteria class

The admin criteria class contains all functionality of the core criteria class.

```js
Component.register('sw-show-case-list', {
    created() {
        const criteria = new Criteria();

        criteria.setPage(1);
        criteria.setLimit(10);
        criteria.setTerm('foo');
        criteria.setIds(['some-id', 'some-id']);
        criteria.setTotalCountMode(2);
        
        criteria.addFilter(
            Criteria.equals('product.active', true)
        );
        
        criteria.addSorting(
            Criteria.sort('product.name', 'DESC')
        );
        
        criteria.addAggregation(
            Criteria.avg('average_price', 'product.price')
        );
        
        criteria.getAssociation('product.categories')
            .addSorting(Criteria.sort('category.name', 'ASC'));
    }
});
```

## How to fetch a single entity

Since the context of an edit or update form is usually a single root entity, the data handling diverges here from the Data Abstraction Layer and provides loading of a single resource from the Admin API

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        this.repository = this.repositoryFactory.create('product');
        const id = 'a-random-uuid';
        
        this.repository
            .get(entityId, Shopware.Context.api)
            .then((entity) => {
                this.entity = entity;
            });
    }
});
```

## Update an entity

The data handling contains change tracking and sends only changed properties to the Admin API. Please be aware that, in order to be as transparent as possible, updating data will not be handled automatically. A manual update is mandatory. 

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        this.repository = this.repositoryFactory.create('product');
        
        const id = 'a-random-uuid';
        
        this.repository
            .get(entityId, Shopware.Context.api)
            .then((entity) => {
                this.entity = entity;
        });
    },
    
    // a function which is called over the ui
    updateTrigger() {
        this.entity.name = 'updated';
        
        // sends the request immediately
        this.repository
            .save(this.entity, Shopware.Context.api)
            .then(() => {            
                // the entity is stateless, the data has be fetched from the server, if required
                this.repository
                    .get(entityId, Shopware.Context.api)
                    .then((entity) => {
                        this.entity = entity;
                    });
            });
    }
});
```

## Delete an entity

In sync with tha Data Abstraction Layer, you do delete the entity through the Admin API by simply sending the `id`, be aware that updating the UI is entirely left to you.

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        this.repository = this.repositoryFactory.create('product');
        
        this.repository.delete('a-random-uuid', Shopware.Context.api);
    }
});

```

## Create an entity

Although entities are detached from the data handling once retrieved or created they still must be set up through a repository. So this is the mandatory way to creating new data.

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        this.repository = this.repositoryFactory.create('product');
        this.entity = this.productRepository.create(Shopware.Context.api);
        
        this.entity.name = 'test';
        this.repository.save(this.entity, Shopware.Context.api);
    }
});
```

## Working with associations

Each association can be accessed via normal property access:

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        this.repository = this.repositoryFactory.create('product');
        
        const id = 'a-random-uuid';
        
        this.repository
            .get(entityId, Shopware.Context.api)
            .then((product) => {
            this.product = product;
            
            // ManyToOne: contains an entity class with the manufacturer data
            console.log(this.product.manufacturer);
            
            // ManyToMany: contains an entity collection with all categories.
            // contains a source property with an api route to reload this data (/product/{id}/categories)
            console.log(this.product.categories);          
            
            // OneToMany: contains an entity collection with all prices
            // contains a source property with an api route to reload this data (/product/{id}/priceRules)            
            console.log(this.product.priceRules);
        });
    }
});
```

## Set a ManyToOne

Changes in related entities are not written automatically. The full control of the entity lifecycle therefore rests in your hands.

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        this.manufacturerRepository = this.repositoryFactory.create('manufacturer');
        
        this.manufacturerRepository
            .get('some-id', Shopware.Context.api)
            .then((manufacturer) => {
                // product is already loaded in this case
                this.product.manufacturer = manufacturer;
                
                // only updates the foreign key for the manufacturer relation               
                this.productRepository.save(this.product, Shopware.Context.api);
        });
    }
});

```

## Working with lazy loaded associations

In most cases, *ToMany* associations are loaded over an additional request. The product prices, for example, are fetched when the prices tab will be activated.

### Working with OneToMany associations

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        this.productRepository = this.repositoryFactory.create('product');
        
        this.productRepository
            .get('some-id', Shopware.Context.api)
            .then((product) => {
                    this.product = product;               
                    this.priceRepository = this.repositoryFactory.create(
                    // `product_price`
                    this.product.prices.entity,                    
                    // `product/some-id/priceRules`
                    this.product.prices.source
                );
            });
        },
        
        loadPrices() {
            this.priceRepository
                .search(new Criteria(), Shopware.Context.api)
                .then((prices) => {
                    this.prices = prices;
                });
        },
        
        addPrice() {
            const newPrice = this.priceRepository.create(Shopware.Context.api);
            
            newPrice.quantityStart = 1;
            // update some other fields
            
            this.priceRepository
                .save(newPrice, Shopware.Context.api)
                .then(this.loadPrices);
        },
            
        deletePrice(priceId) {
            this.priceRepository
                .delete(priceId, Shopware.Context.api)
                .then(this.loadPrices);
        },
        
        updatePrice(price) {
            this.priceRepository
                .save(price, Shopware.Context.api)
                .then(this.loadPrices);
        }
});

```

### Working with ManyToMany associations

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        this.productRepository = this.repositoryFactory.create('product');
        
        this.productRepository
            .get('some-id', Shopware.Context.api)
            .then((product) => {
                this.product = product;
        
                // creates a repository which working with the associated route
                this.catRepository = this.repositoryFactory.create(
                    // `category`
                    this.product.categories.entity,                   
                    // `product/some-id/categories`        
                    this.product.categories.source
            );
        });
    },
    
    loadCategories() {
        this.catRepository
            .search(new Criteria(), Shopware.Context.api)
            .then((categories) => {
                this.categories = categories;
            });
    },
    
    addCategoryToProduct(category) {
        this.catRepository
            .assign(category.id, Shopware.Context.api)
            .then(this.loadCategories);
    },
    
    removeCategoryFromProduct(categoryId) {
        this.catRepository
            .delete(categoryId, Shopware.Context.api)
            .then(this.loadCategories);
    }
});
```

## Working with local associations

In case of a new entity, the associations can not be sent directly to the server using the repository, because the parent association isn't saved yet.

For this case the association can be used as storage as well and will be updated with the parent entity. In the following examples, `this.productRepository.save(this.product, Shopware.Context.api)` will send the prices and category changes.

Notice: It is mandatory to `add` entities to collections in order to get reactive data for the UI.

### Working with local OneToMany associations

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        this.productRepository = this.repositoryFactory.create('product');
        
        this.productRepository
            .get('some-id', Shopware.Context.api)
            .then((product) => {
                this.product = product;
                
                this.priceRepository = this.repositoryFactory.create(
                    // `product_price`
                    this.product.prices.entity,                
                    // `product/some-id/priceRules`
                    this.product.prices.source
                );
        });
    },
    
    loadPrices() {
        this.prices = this.product.prices;
    },
    
    addPrice() {
        const newPrice = this.priceRepository
            .create(Shopware.Context.api);
        
        newPrice.quantityStart = 1;
        // update some other fields
        
        this.product.prices.add(newPrice);
    },
    
    deletePrice(priceId) {
        this.product.prices.remove(priceId);
    },
    
    updatePrice(price) {
        // price entity is already updated and already assigned to product, no sources needed
    }
});
```

### Working with local ManyToMany associations

```js
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        this.productRepository = this.repositoryFactory.create('product');
        
        this.productRepository
            .get('some-id', Shopware.Context.api)
            .then((product) => {
                this.product = product;
                
                // creates a repository which working with the associated route
                
                this.catRepository = this.repositoryFactory.create(
                    // `category`
                    this.product.categories.entity,                    
                    // `product/some-id/categories`
                    this.product.categories.source
                );
            });
    },
    
    loadCategories() {
        this.categories = this.product.categories;
    },
    
    addCategoryToProduct(category) {
        this.product.categories.add(category);
    },
    
    removeCategoryFromProduct(categoryId) {
        this.product.categories.remove(categoryId);
    }
});
```
