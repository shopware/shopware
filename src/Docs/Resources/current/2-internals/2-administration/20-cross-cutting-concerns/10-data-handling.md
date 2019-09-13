[titleEn]: <>(Data handling)

The data handling was created with **predictability** as its main design goal. It uses a *repository pattern* which is strongly based on the [Database Abstraction Layer](./../../1-core/20-data-abstraction-layer/__categoryInfo.md) from the core.

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

```javascript
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory'],
    
    created() {
        this.repository = this.repositoryFactory.create('product');
    }
});
```

## How to fetch listings

To fetch data from the server, the repository has a `search` function. Each repository function requires the `context`. This can be injected like the repository factory:

```javascript
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory', 'context'],
    
    created() {
        // create a repository for the `product` entity
        this.repository = this.repositoryFactory.create('product');
        
        this.repository
            .search(new Criteria(), this.context)
            .then((result) => {
                this.result = result;
            });
    }
});
```

## Working with the criteria class

The admin criteria class contains all functionality of the core criteria class.

```javascript
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

```javascript
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory', 'context'],
    
    created() {
        this.repository = this.repositoryFactory.create('product');
        const id = 'a-random-uuid';
        
        this.repository
            .get(entityId, this.context)
            .then((entity) => {
                this.entity = entity;
            });
    }
});
```
## Update an entity

The data handling contains change tracking and sends only changed properties to the Admin API. Please be aware that, in order to be as transparent as possible, updating data will not be handled automatically. A manual update is mandatory. 

```javascript
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory', 'context'],
    
    created() {
        this.repository = this.repositoryFactory.create('product');
        
        const id = 'a-random-uuid';
        
        this.repository
            .get(entityId, this.context)
            .then((entity) => {
                this.entity = entity;
        });
    },
    
    // a function which is called over the ui
    updateTrigger() {
        this.entity.name = 'updated';
        
        // sends the request immediately
        this.repository
            .save(this.entity, this.context)
            .then(() => {            
                // the entity is stateless, the data has be fetched from the server, if required
                this.repository
                    .get(entityId, this.context)
                    .then((entity) => {
                        this.entity = entity;
                    });
            });
    }
});
```

## Delete an entity

In sync with tha Data Abstraction Layer, you do delete the entity through the Admin API by simply sending the `id`, be aware that updating the UI is entirely left to you.

```javascript
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory', 'context'],
    
    created() {
        this.repository = this.repositoryFactory.create('product');
        
        this.repository.delete('a-random-uuid', this.context);
    }
});

```

## Create an entity

Although entities are detached from the data handling once retrieved or created they still must be set up through a repository. So this is the mandatory way to creating new data.

```javascript
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory', 'context'],
    
    created() {
        this.repository = this.repositoryFactory.create('product');
        this.entity = this.productRepository.create(this.context);
        
        this.entity.name = 'test';
        this.repository.save(this.entity, this.context);
    }
});
```

## Working with associations

Each association can be accessed via normal property access:

```javascript
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory', 'context'],
    
    created() {
        this.repository = this.repositoryFactory.create('product');
        
        const id = 'a-random-uuid';
        
        this.repository
            .get(entityId, this.context)
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

```javascript
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory', 'context'],
    
    created() {
        this.manufacturerRepository = this.repositoryFactory.create('manufacturer');
        
        this.manufacturerRepository
            .get('some-id', this.context)
            .then((manufacturer) => {
                // product is already loaded in this case
                this.product.manufacturer = manufacturer;
                
                // only updates the foreign key for the manufacturer relation               
                this.productRepository.save(this.product, this.context);
        });
    }
});

```

## Working with lazy loaded associations

In most cases, *ToMany* assocations are loaded over an additional request. The product prices, for example, are fetched when the prices tab will be activated.

### Working with OneToMany associations

```javascript
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory', 'context'],
    
    created() {
        this.productRepository = this.repositoryFactory.create('product');
        
        this.productRepository
            .get('some-id', this.context)
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
                .search(new Criteria(), this.context)
                .then((prices) => {
                    this.prices = prices;
                });
        },
        
        addPrice() {
            const newPrice = this.priceRepository.create(this.context);
            
            newPrice.quantityStart = 1;
            // update some other fields
            
            this.priceRepository
                .save(newPrice, this.context)
                .then(this.loadPrices);
        },
            
        deletePrice(priceId) {
            this.priceRepository
                .delete(priceId, this.context)
                .then(this.loadPrices);
        },
        
        updatePrice(price) {
            this.priceRepository
                .save(price, this.context)
                .then(this.loadPrices);
        }
});

```

### Working with ManyToMany associations

```javascript
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory', 'context'],
    
    created() {
        this.productRepository = this.repositoryFactory.create('product');
        
        this.productRepository
            .get('some-id', this.context)
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
            .search(new Criteria(), this.context)
            .then((categories) => {
                this.categories = categories;
            });
    },
    
    addCategoryToProduct(category) {
        this.catRepository
            .assign(category.id, this.context)
            .then(this.loadCategories);
    },
    
    removeCategoryFromProduct(categoryId) {
        this.catRepository
            .delete(categoryId, this.context)
            .then(this.loadCategories);
    }
});
```
## Working with local associations

In case of a new entity, the associations can not be sent directly to the server using the repository, because the parent association isn't saved yet.

For this case the association can be used as storage as well and will be updated with the parent entity. In the following examples, `this.productRepository.save(this.product, this.context)` will send the prices and category changes.

Notice: It is mandatory to `add` entities to collections in order to get reactive data for the UI.

### Working with local OneToMany associations

```javascript
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory', 'context'],
    
    created() {
        this.productRepository = this.repositoryFactory.create('product');
        
        this.productRepository
            .get('some-id', this.context)
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
            .create(this.context);
        
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

```javascript
Component.register('sw-show-case-list', {
    inject: ['repositoryFactory', 'context'],
    
    created() {
        this.productRepository = this.repositoryFactory.create('product');
        
        this.productRepository
            .get('some-id', this.context)
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

## We came here the hard way

At the time of writing there is still a second implementation of data handling present. This one is **deprecated** and we explicitly discourage using it for new components! The **old** data handling on the surface does a lot more magic for you and reduces the amount of boilerplate code, but the level of convenience it provides will make it extremely difficult to do anything with it besides the basic CRUD operations.
