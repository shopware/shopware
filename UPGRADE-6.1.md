UPGRADE FROM 6.0 to 6.1
=======================

Core
----

* If you have implemented a custom FieldResolver, you need to implement the `getJoinBuilder` method.

Administration
--------------

**Important Change:** The admin core framework of shopware from `src/core/` should always be accessed via the global available `Shopware` object and not via static imports. This is important to provide a consistent access point to the core framework of the shopware administration, either you are using Webpack or not. It will also ensure the correct bundling of source files via Webpack. Especially third party plugins have to ensure to access the core framework only via the global `Shopware` object. Using the concept of destructuring can help to access just specific parts of the framework and maintain readability of your code. Nevertheless you can use static imports in your plugins to import other source files of your plugin or NPM dependencies.

Before:

```
import { Component } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './my-component.html.twig';

Component.register('my-component', {
    template,

    inject: ['repositoryFactory', 'context'],

    data() {
        return {
            products: null
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        }
    },

    created() {
        this.getProducts();
    },

    methods: {
        getProducts() {
            const criteria = new Criteria();
            criteria.addAssociation('manufacturer');
            criteria.addSorting(Criteria.sort('product.productNumber', 'ASC'));
            criteria.limit = 10;

            return this.productRepository
            .search(criteria, this.context)
                .then((result) => {
                    this.products = result;
                });
        }
    }
});
```

After:

```
import template from './my-component.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('my-component', {
    template,

    inject: ['repositoryFactory', 'context'],

    data() {
        return {
            products: null
        };
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        }
    },

    created() {
        this.getProducts();
    },

    methods: {
        getProducts() {
            const criteria = new Criteria();
            criteria.addAssociation('manufacturer');
            criteria.addSorting(Criteria.sort('product.productNumber', 'ASC'));
            criteria.limit = 10;

            return this.productRepository
            .search(criteria, this.context)
                .then((result) => {
                    this.products = result;
                });
        }
    }
});

```

Storefront
----------

**Changes**

* A theme must now implement the `Shopware\Storefront\Framework\ThemeInterface`.

Elasticsearch
-------------

*No changes yet*
