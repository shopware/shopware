[titleEn]: <>(New admin data handling)
[__RAW__]: <>(__RAW__)

<p>The new data handling was created to remove the active record pattern in the admininstration. It uses a repository pattern which is strongly based on the DAL from the PHP part.</p>

<p><strong>Relevant classes</strong></p>

<ul>
	<li>Repository
	<ul>
		<li>Allows to send requests to the server - used for all CRUD operations</li>
	</ul>
	</li>
	<li>Entity
	<ul>
		<li>Object for a single storage record</li>
	</ul>
	</li>
	<li>Entity Collection
	<ul>
		<li>Enable object-oriented access to a collection of entities</li>
	</ul>
	</li>
	<li>Search Result
	<ul>
		<li>Contains all information available through a search request</li>
	</ul>
	</li>
	<li>RepositoryFactory
	<ul>
		<li>Allows to create a repository for an entity</li>
	</ul>
	</li>
	<li>Context
	<ul>
		<li>Contains the global state of administration (Language, Version, Auth, ...)</li>
	</ul>
	</li>
	<li>Criteria
	<ul>
		<li>Contains all information for a search request (filter, sorting, pagination, ...)</li>
	</ul>
	</li>
</ul>

<p><strong>Get access to a repository</strong><br />
To create a repository it is required to inject the RepositoryFactory:</p>

<pre>
Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;],
    
    created() {
        this.repository = this.repositoryFactory.create(&#39;product&#39;);
    }
});</pre>

<p><br />
<strong>How to fetch listings</strong><br />
To fetch data from the server, the repository has a <strong>search</strong> function. Each repository function requires the api context. This can be accessed with the Shopware object: `Shopware.Context.api`.</p>

<pre>
Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;],
    
    created() {
        // create a repository for the `product` entity
        this.repository = this.repositoryFactory.create(&#39;product&#39;);
    
        this.repository
            .search(new Criteria(), Shopware.Context.api)
            .then((result) =&gt; {
                this.result = result;
            });
    }
});</pre>

<p><br />
<strong>Working with the criteria class</strong><br />
The new admin criteria class contains all functionalities of the php criteria class.</p>

<pre>
Component.register(&#39;sw-show-case-list&#39;, {
    created() {
        const criteria = new Criteria();
        criteria.setPage(1);
    
        criteria.setLimit(10);
    
        criteria.setTerm(&#39;foo&#39;);
    
        criteria.setIds([&#39;some-id&#39;, &#39;some-id&#39;]);
    
        criteria.setTotalCountMode(2);
    
        criteria.addFilter(
            Criteria.equals(&#39;product.active&#39;, true)
        );
    
        criteria.addSorting(
            Criteria.sort(&#39;product.name&#39;, &#39;DESC&#39;)
        );
    
        criteria.addAggregation(
            Criteria.avg(&#39;average_price&#39;, &#39;product.price&#39;)
        );
    
        const categoryCriteria = new Criteria();
        categoryCriteria.addSorting(
            Criteria.sort(&#39;category.name&#39;, &#39;ASC&#39;)
        );
    
        criteria.addAssociation(&#39;product.categories&#39;, categoryCriteria);
    }
});</pre>

<p><br />
<strong>How to fetch a single entity</strong></p>

<pre>
<strong>
</strong>Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;],
    
    created() {
        this.repository = this.repositoryFactory.create(&#39;product&#39;);
    
        const id = &#39;a-random-uuid&#39;;
    
        this.repository
            .get(entityId, Shopware.Context.api)
            .then((entity) =&gt; {
                this.entity = entity;
            });
    }
});
Update an entity
Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;],
    
    created() {
        this.repository = this.repositoryFactory.create(&#39;product&#39;);
    
        const id = &#39;a-random-uuid&#39;;
    
        this.repository
            .get(entityId, Shopware.Context.api)
            .then((entity) =&gt; {
                this.entity = entity;
            });
    },
    
    // a function which is called over the ui
    updateTrigger() {
        this.entity.name = &#39;updated&#39;;
    
        // sends the request immediately
        this.repository
            .save(this.entity, Shopware.Context.api)
            .then(() =&gt; {
    
                // the entity is stateless, the new data has be fetched from the server, if required
                this.repository
                    .get(entityId, Shopware.Context.api)
                    .then((entity) =&gt; {
                    this.entity = entity;
                });
            });
    }
});</pre>

<p><br />
<strong>Delete an entity</strong></p>

<pre>
<strong>
</strong>Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;],
    
    created() {
        this.repository = this.repositoryFactory.create(&#39;product&#39;);
    
        this.repository.delete(&#39;a-random-uuid&#39;, Shopware.Context.api);
    }
});
Create an entity
Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;],
    
    created() {
        this.repository = this.repositoryFactory.create(&#39;product&#39;);
    
        this.entity = this.productRepository.create(Shopware.Context.api);
    
        this.entity.name = &#39;test&#39;;
    
        this.repository.save(this.entity, Shopware.Context.api);
    }
});</pre>

<p><br />
<strong>Working with associations</strong><br />
Each association can be accessed via normal property access:</p>

<pre>
Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;],
    
    created() {
        this.repository = this.repositoryFactory.create(&#39;product&#39;);
    
        const id = &#39;a-random-uuid&#39;;
    
        this.repository
            .get(entityId, Shopware.Context.api)
            .then((product) =&gt; {
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
});</pre>

<p><br />
<strong>Set a ManyToOne</strong></p>

<pre>
<strong>
</strong>Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;],
    
    created() {
        this.manufacturerRepository = this.repositoryFactory.create(&#39;manufacturer&#39;);
    
        this.manufacturerRepository
            .get(&#39;some-id&#39;, Shopware.Context.api)
            .then((manufacturer) =&gt; {
    
                // product is already loaded in this case
                this.product.manufacturer = manufacturer;
    
                // only updates the foreign key for the manufacturer relation
                
                this.productRepository
                    .save(this.product, Shopware.Context.api);
            });
    }
});</pre>

<p><br />
<strong>Working with lazy loaded associations</strong><br />
In most cases, ToMany assocations are loaded over an additionally request. Like the product prices are fetched when the prices tab will be activated.</p>

<p><strong>Working with OneToMany associations</strong></p>

<pre>
Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;],
    
    created() {
        this.productRepository = this.repositoryFactory.create(&#39;product&#39;);
    
        this.productRepository
            .get(&#39;some-id&#39;, Shopware.Context.api)
            .then((product) =&gt; {
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
            .then((prices) =&gt; {
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
});</pre>

<p><br />
<strong>Working with ManyToMany associations</strong></p>

<pre>
<strong>
</strong>Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;],
    
    created() {
        this.productRepository = this.repositoryFactory.create(&#39;product&#39;);
    
        this.productRepository
            .get(&#39;some-id&#39;, Shopware.Context.api)
            .then((product) =&gt; {
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
            .then((categories) =&gt; {
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
});</pre>

<p><br />
<strong>Working with local associations</strong><br />
In case of a new entity, the associations can not be send directly to the server using the repository, because the parent association isn&#39;t saved yet.</p>

<p>For this case the association can be used as storage as well and will be updated with the parent entity.</p>

<p>In the following examples, this.productRepository.save(this.product, Shopware.Context.api) will send the prices and category changes.</p>

<p><strong>Working with local OneToMany associations</strong></p>

<pre>
Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;, &#39;context&#39;],
    
    created() {
        this.productRepository = this.repositoryFactory.create(&#39;product&#39;);
    
        this.productRepository
            .get(&#39;some-id&#39;, this.context)
            .then((product) =&gt; {
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
});</pre>

<p><br />
<strong>Working with local ManyToMany associations</strong></p>

<pre>
<strong>
</strong>Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;, &#39;context&#39;],
    
    created() {
        this.productRepository = this.repositoryFactory.create(&#39;product&#39;);
    
        this.productRepository
            .get(&#39;some-id&#39;, this.context)
            .then((product) =&gt; {
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
});</pre>

<p><br />
<strong>Working with version</strong><br />
The new data handling supports the php DAL versioning too. This allows the user to make changes that are not applied directly to the live shop. This is required when content such as products, CMS pages, orders are processed where the user needs the possibility to revert the changes.</p>

<pre>
Component.register(&#39;sw-show-case-list&#39;, {
    inject: [&#39;repositoryFactory&#39;, &#39;context&#39;],
    
    created() {
        this.productRepository = this.repositoryFactory.create(&#39;product&#39;);
    
        this.entityId = &#39;some-id&#39;;
    
        this.productRepository
            .createVersion(this.entityId, this.context)
            .then((versionContext) =&gt; {
                // the version context contains another version id
                this.versionContext = versionContext;
            })
            .then(() =&gt; {
                // association has a reference to this version context
                return this.productRepository
                    .get(this.entityId, this.versionContext);
            })
            .then((entity) =&gt; {
                this.product = entity;
                return entity;
            });
    },
    
    cancel() {
        this.productRepository.deleteVersion(this.entityId, this.versionContext.versionId, this.versionContext);
    },
    
    merge() {
        this.productRepository
            .save(this.product, this.versionContext)
            .then(() =&gt; {
                this.productRepository.mergeVersion(
                    this.versionContext.versionId, 
                    this.versionContext
                );
        });
    }
});</pre>
