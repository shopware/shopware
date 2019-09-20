UPGRADE FROM 6.0 to 6.1
=======================

Core
----

* If you have implemented a custom FieldResolver, you need to implement the `getJoinBuilder` method.
* `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria` association handling

    We removed the `$criteria` parameter from the `addAssociation` function. By setting the criteria object the already added criteria was overwritten. This led to problems especially with multiple extensions by plugins. Furthermore the function `addAssociationPath` was removed from the criteria. The following functions are now available on the criteria object:

    * `addAssociation(string $path): self`

        This function allows you to load additional associations. The transferred path can also point to deeper levels:

        `$criteria->addAssociation('categories.media.thumbnails);`

        For each association in the provided path, a criteria object with the corresponding association is now ensured. If a criteria is already stored, it will no longer be overwritten.

    * `getAssociation(string $path): Criteria`

        This function allows access to the criteria for an association. If the association is not added to the criteria, it will be created automatically. The provided path can also point to deeper levels:

        ```
        $criteria = new Criteria();
        $thumbnailCriteria = $criteria->getAssociation('categories.media.thumbnail');
        $thumbnailCriteria->setLimit(5);
        ```
 * Added RouteScopes as required Annotation for all Routes

    We have added Scopes for Routes. The Scopes hold and resolve information of allowed paths and contexts.
    A RouteScope is mandatory for a Route. From now on every Route defined, needs a defined RouteScope.

    RouteScopes are defined via Annotation:
    ```php
    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/account/login", name="frontend.account.login.page", methods={"GET"})
     */

     /**
      * @RouteScope(scopes={"storefront", "my_additional_scope"})
      * @Route("/account/login", name="frontend.account.login.page", methods={"GET"})
      */

    ```

* If you have implemented a custom `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface`, you need to implement the `partial` method.
    Here are two good example implementations:
    1. simple iteration: `\Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductCategoryTreeIndexer::partial`
    2. iteration with several ids: `\Shopware\Core\Content\Category\DataAbstractionLayer\Indexing\BreadcrumbIndexer::partial`

* If you have implemented the `\Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface`, you need to return now a `\Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection`
* We changed the constructor parameter order of `\Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation`
* Aggregations are now returned directly in a collection:
    ```php
    $criteria->addAggregation(
        new SumAggregation('sum-price', 'product.price')
    );
    
    $result = $this->repository->search($criteria, $context);

    /** @var SumResult $sum */
    $sum = $result->getAggregations()->get('sum-price');
    
    $sum->getSum();
    ```
* `ValueCountAggregation` and `ValueAggregation` removed, use `TermsAggregation` instead. 
    ```php
    $criteria->addAggregation(
        new TermsAggregation('category-ids', 'product.categories.id')
    );
    
    $result = $this->repository->aggregate($criteria, $context);
    
    /** @var TermsResult $categoryAgg */
    $categoryAgg = $result->get('category-ids');
    
    foreach ($categoryAgg->getBuckets() as $bucket) {
        $categoryId = $bucket->getKey();
        $count = $bucket->getCount();
    }
    ```
* We changed the type hint of `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent::getResult` to `AggregationResultCollection`
* We removed the `Aggregation::groupByFields` and `Aggregation::filters` property, use `\Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation` and `\Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation` instead
    ```php
    $criteria->addAggregation(
        new FilterAggregation(
            'filter',
            new TermsAggregation('category-ids', 'product.categories.id'),
            [new EqualsAnyFilter('product.active', true)]
        )
    );
    ```

* We removed the default api limit for association. Associations are no longer paginated by default. In order to load the association paginated, the limit and page parameter can be sent along:
    ```json
    {
       "associations": {
          "categories": {
              "page": 2,
              "limit": 5
          }
       }
    }
    ```

* If you have implemented a custom `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer`, you must now provide a `DefinitionInstanceRegistry` when calling the super constructor
* Removed `Shopware\Core\Framework\DataAbstractionLayer\EntityWrittenContainerEvent::getEventByDefinition`. Use `getEventByEntityName` instead, which takes the entity name instead of the entity classname but proved the same functionality.
* Removed `getDefinition` and the corresponding `definition` member from `\Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResults` and `...\Event\EntityWrittenEvent`. Classes which used this function can access the name of the written entity via the new method `getEntityName` and retrieve the definition using the `DefinitionInstanceRegistry`

Administration
--------------

* **Important Change:** The admin core framework of shopware from `src/core/` should always be accessed via the global available `Shopware` object and not via static imports. This is important to provide a consistent access point to the core framework of the shopware administration, either you are using Webpack or not. It will also ensure the correct bundling of source files via Webpack. Especially third party plugins have to ensure to access the core framework only via the global `Shopware` object. Using the concept of destructuring can help to access just specific parts of the framework and maintain readability of your code. Nevertheless you can use static imports in your plugins to import other source files of your plugin or NPM dependencies.

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

* Replaced vanilla-colorpicker dependency with custom-build vuejs colorpicker
  * `editorFormat` and `colorCallback` got replaced with `colorOutput`
  * the default value for the property `alpha` is now `true`

  Before:
  ```
  <sw-colorpicker value="myColorVariable"
                  editorFormat="rgb"
                  colorCallback="rgbString">
  </sw-colorpicker>
  ```

  After:
  ```
  <sw-colorpicker value="myColorVariable"
                  colorOutput="rgb"
                  :alpha="false">
  </sw-colorpicker>
  ```

* **Important Change:** The Shopping Experiences data handling has changed. To get an entity resolved in an element you now need to configure a configfield like this:
```
    product: {
        source: 'static',
        value: null,
        required: true,
        entity: {
            name: 'product',
            criteria: criteria
        }
    }
```
Where the criteria is the required criteria for this entity
(in this case `const criteria = new Criteria(); criteria.addAssociation('cover');`).
Furthermore you can now define your custom `collect` and `enrich` method in the `cmsService.registerCmsElement` method.
See `2019-09-02-cms-remove-store.md` for more information

* Refactored select components and folder structure
    * Select components are now located in the folder `administration/src/app/component/form/select` divided in the subfolders `base` and `entity`
        * `base` contains the base components for creating new selects and the static `sw-single-select` and `sw-multi-select`
        * `entity` contains components working with the api such as `sw-entity-multi-select` or `sw-entity-tag-select`
    * Components work with v-model and do not mutate the value property anymore
    * Components are based on the sw-field base components to provide a consistent styling, error handling etc for all form fields
* We unified the implementation of `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria` and the Admin criteria `src/core/data-new/criteria.data.js`
    * Removed `addAssociationPath`
    * Changed signature of `addAssociation`
        ```js
        addAssociation(path);
        ```
    * `addAssociation` now only ensures that the association is loaded as well. No Criteria can be passed anymore. The function supports instead the specification of a nested path. The function always returns the criteria it is called on to allow chaining to continue. 
         
        ```js
        const criteria = new Criteria();
        criteria.addAssociation('product.categories.media')
           .addAssociation('product.cover')
           .addAssociation('product.media');
        ```
    * The `getAssociation` function now accepts the passing of a nested path. Furthermore, the function now ensures that a criteria is created for each path segment. Then it returns the criteria for the last path segment.
        ```js
        const criteria = new Criteria();
        const mediaCriteria = criteria.getAssociation('product.categories.media');
        mediaCriteria.addSorting(Criteria.sort('media.fileName', 'ASC'))
        mediaCriteria.addFilter(Criteria.equals('fileName', 'testImage'));
        ```

Storefront
----------

**Changes**

* A theme must now implement the `Shopware\Storefront\Framework\ThemeInterface`.
* If your javascript lives in `Resources/storefront/script` you have to explicitly define this path in the `getStorefrontScriptPath()` method of your plugin base class as we have changed the default path to `Resources/dist/storefront/js`.
* Added `extractIdsToUpdate` to `Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteInterface`. `extractIdsToUpdate` must provide the ids of entities which seo urls should be updated based on an EntityWrittenContainerEvent.

Elasticsearch
-------------

*No changes yet*
