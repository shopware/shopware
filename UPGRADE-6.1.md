UPGRADE FROM 6.0 to 6.1
=======================

Core
----

* `\Shopware\Storefront\Controller\StorefrontController::forwardToRoute` now handles parameters correctly.
* Request scope changes during a single request handle are prohibited.
* Use `\Shopware\Core\Framework\Routing\RequestTransformerInterface::extractInheritableAttributes` if you want to create a true subrequest.
* The Context will only be resolved when a valid scope is dipatched.
* All admin and api routes are now authentication protected by default.
* Changed the `\Symfony\Component\HttpKernel\KernelEvents::CONTROLLER` Event-Subscriber priorities. Now all Shopware Listeners are handled after the core symfony event handlers. You can find the priorities in `\Shopware\Core\Framework\Routing\KernelListenerPriorities`.
* Removed the `Shopware\Core\Framework\Routing\Event\RouteScopeWhitlistCollectEvent` in favor of a taggable interface named `Shopware\Core\Framework\Routing\RouteScopeWhitelistInterface`.
* Requests can no longer be forwarded across different request scopes.
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
* We've changed the kernel plugin loading. Replace the `ClassLoader` with an instance of `\Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader`.

    Before:
    ```php
    $kernel = new \Shopware\Core\Kernel($env, $debug, $classLoader, $version);
    ```

    After:
    ```php
    $connection = \Shopware\Core\Kernel::getConnection();
    $pluginLoader = new \Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader($classLoader, null, $connection);
    $kernel = new \Shopware\Core\Kernel($env, $debug, $pluginLoader, $version);

    // or without plugins
    $pluginLoader = new \Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader($classLoader, null, []);
    $kernel = new \Shopware\Core\Kernel($env, $debug, $pluginLoader, $version);

    // or with a static plugin list
    $plugins = [
        [
            'baseClass' => 'SwagTest\\SwagTest',
            'active' => true,
            'path' => 'platform/src/Core/Framework/Test/Plugin/_fixture/plugins/SwagTest',
            'autoload' => ['psr-4' => ['SwagTest\\' => 'src/']],
            'managedByComposer' => false,
        ]
    ];
    $pluginLoader = new \Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader($classLoader, null, $plugins);
    $kernel = new \Shopware\Core\Kernel($env, $debug, $pluginLoader, $version);
    ```

* the parameter for the `\Shopware\Core\Kernel::boot` method was removed. Instead, use the `StaticKernelPluginLoader` with an empty list.
* If you have implemented a custom `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer`, you must now provide a `DefinitionInstanceRegistry` when calling the super constructor
* Removed `Shopware\Core\Framework\DataAbstractionLayer\EntityWrittenContainerEvent::getEventByDefinition`. Use `getEventByEntityName` instead, which takes the entity name instead of the entity classname but proved the same functionality.
* Removed `getDefinition` and the corresponding `definition` member from `\Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResults` and `...\Event\EntityWrittenEvent`. Classes which used this function can access the name of the written entity via the new method `getEntityName` and retrieve the definition using the `DefinitionInstanceRegistry`
* Replace service id `shopware.cache` with `cache.object`
* If you invalidated the entity cache over the `shopware.cache` service, use the `\Shopware\Core\Framework\Adapter\Cache\CacheClearer` instead.
* All customer events in `Shopware\Core\Checkout\Customer\Event` now get the `Shopware\Core\Syste\SalesChannel\SalesChannelContext` instead of `Shopware\Core\Framework\Context` and a `salesChannelId`
* Implement `getName` for classes that implement `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface`
* We've moved the seo module into the core. Replace the namespace `Shopware\Storefront\Framework\Seo\` with `Shopware\Core\Content\Seo\`
* Switch the usage of `\Shopware\Core\Framework\Migration\MigrationStep::addForwardTrigger()` and `\Shopware\Core\Framework\Migration\MigrationStep::addBackwardTrigger()`, as the execution conditions were switched. 
* `\Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity::$trackingCode` has been replaced with `\Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity::$trackingCodes`.
* Add Bearer Auth Token to requests to `/api/v{version}/_info/entity-schema.json` and `/api/v{version}/_info/business-events.json` routes
* Removed `shopware.api.api_browser.public` config value, use `shopware.api.api_browser.auth_required = true` instead, to limit access to the open api routes
* Replace `product/category.extensions.seoUrls` with `product/category.seoUrls`
* Dropped `additionalText` column of product entity, use `metaDescription` instead
* If your entity definition overwrites the `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::getDefaults` method, you will have to remove the parameter, as it is not needed anymore. Remove the check `$existence->exists()` as this is done before by the Core now. If you want to define different defaults for child entities, overwrite `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::getChildDefaults`
* If you depend on `\Shopware\Core\Framework\Context::createDefaultContext()` outside of tests, pass the context as a parameter to your method instead
* The Shopware entity cache has been removed and has been replaced by a symfony cache pool. You have to remove any configuration files pointing to `shopware.entity_cache`.

    Example: Redis implementation
    ```yaml
    framework:
        cache:
            app: cache.adapter.redis
            default_redis_provider: 'redis://redis.server'
    ```

    Example: Disable the object cache only
    ```yaml
    framework:
        cache:
            pools:
                cache.object:
                    adapter: cache.adapter.array
    ```

    Example: Disable the cache entirely
    ```yaml
    framework:
        cache:
            app: cache.adapter.array
    ```
  
 * Add the `extractInheritableAttributes()` function to your implementations of `\Shopware\Core\Framework\Routing\RequestTransformerInterface`
 * Find and replace `Shopware\Core\Framework\Acl` with `Shopware\Core\Framework\Api\Acl`
 * Find and replace `Shopware\Core\Framework\CustomField` with `Shopware\Core\System\CustomField`
 * Find and replace `Shopware\Core\Framework\Language` with `Shopware\Core\System\Language`
 * Find and replace `Shopware\Core\Framework\Snippet` with `Shopware\Core\System\Snippet`
 * Find and replace `Shopware\Core\Framework\Doctrine` with `Shopware\Core\Framework\DataAbstractionLayer\Doctrine`
 * Find and replace `Shopware\Core\Framework\Pricing` with `Shopware\Core\Framework\DataAbstractionLayer\Pricing`
 * Find and replace `Shopware\Core\Framework\Version` with `Shopware\Core\Framework\DataAbstractionLayer\Version`
 * Find and replace `Shopware\Core\Framework\Faker` with `Shopware\Core\Framework\Demodata\Faker`
 * Find and replace `Shopware\Core\Framework\PersonalData` with `Shopware\Core\Framework\Demodata\PersonalData`
 * Find and replace `Shopware\Core\Framework\Logging` with `Shopware\Core\Framework\Log`
 * Find and replace `Shopware\Core\Framework\ScheduledTask` with `Shopware\Core\Framework\MessageQueue\ScheduledTask`
 * Find and replace `Shopware\Core\Framework\Twig` with `Shopware\Core\Framework\Adapter\Twig`
 * Find and replace `Shopware\Core\Framework\Asset` with `Shopware\Core\Framework\Adapter\Asset`
 * Find and replace `Shopware\Core\Framework\Console` with `Shopware\Core\Framework\Adapter\Console`
 * Find and replace `Shopware\Core\Framework\Cache` with `Shopware\Core\Framework\Adapter\Cache`
 * Find and replace `Shopware\Core\Framework\Filesystem` with `Shopware\Core\Framework\Adapter\Filesystem`
 * Find and replace `Shopware\Core\Framework\Translation` with `Shopware\Core\Framework\Adapter\Translation`
 * Find and replace `Shopware\Core\Framework\Seo` with `Shopware\Core\Content\Seo`
 * Find and replace `Shopware\Core\Content\DeliveryTime` with `Shopware\Core\System\DeliveryTime`
 * Find and replace `Shopware\Core\Framework\Context\` with `Shopware\Core\Framework\Api\Context\`
 * Find and replace `Shopware\Core\System\User\Service\UserProvisioner` with `Shopware\Core\System\User\Service\UserProvisioner`
    * Warning: Do not replace `Shopware\Core\Framework\Context` with `Shopware\Core\Framework\Api\Context`, this would replace the `Framework\Context.php` usage.
 * Added unique constraint for `iso_code` column of `currency` table. The migration can fail if there are already duplicate `iso_codes` in the table
 * Replace `mailer` usage with `core_mailer` in your service definitions. 
 * If you call `\Shopware\Core\Framework\Api\Response\ResponseFactoryInterface::createDetailResponse` or `\Shopware\Core\Framework\Api\Response\ResponseFactoryInterface::createListingResponse` in your plugin, the first parameter to be passed now is the `Criteria` object with which the data was loaded.
 * We changed the type hint of `Shopware\Core\Framework\Validation\ValidationServiceInterface::buildCreateValidation` and `Shopware\Core\Framework\Validation\ValidationServiceInterface::buildUpdateValidation` to `SalesChannelContext`
 * Replace `\Shopware\Core\Framework\Plugin::getExtraBundles` with `\Shopware\Core\Framework\Plugin::getAdditionalBundles`. Dont use both.
 * We implemented the new `Shopware\Core\HttpKernel` class which simplifies the kernel initialisation. This kernel can simply initialed and can be used in your `index.php` file as follow:
    ```php
    $request = Request::createFromGlobals();

    $kernel = new HttpKernel($appEnv, $debug, $classLoader);
    $result = $kernel->handle($request);

    $result->getResponse()->send();

    $kernel->terminate($result->getRequest(), $result->getResponse());
    ```
 * If you used the `\Shopware\Core\Content\Seo\SeoUrlGenerator` in your sources, please use the `generate` function instead of the `generateSeoUrls`
 
 * If you update your decoration implementations of `\Shopware\Core\Framework\Validation\ValidationServiceInterface` to  `\Shopware\Core\Framework\Validation\DataValidationFactoryInterface` make sure to still implement the old interface
    and when calling the inner implementation please make sure to check if the inner implementation already supports the interface, like
    ```php
       public function createValidation(SalesChannelContext $context): DataValidationDefinition
       {
           if ($this->inner instanceof DataValidationFactoryInterface) {
               $validation = $this->inner->create($context);
           } else {
               $validation = $this->inner->buildCreateValidation($context->getContext());
           }
   
           $this->modifyValidation($validation);
   
           return $validation;              
       }
    ```
 * We will change the `\Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator::getEntityTag` signature from
    * Before: `public function getEntityTag(string $id, EntityDefinition $definition): string`
    * After:  `public function getEntityTag(string $id, string $entityName): string`
    * If you called this function, simply replace the second function parameter to your entity name
    * Currently both ways are supported. The `string $entityName` type hint will be added with `v6.3.0`

Administration
--------------

* The admin core framework of shopware from `src/core/` should always be accessed via the global available `Shopware` object and not via static imports. This is important to provide a consistent access point to the core framework of the shopware administration, either you are using Webpack or not. It will also ensure the correct bundling of source files via Webpack. Especially third party plugins have to ensure to access the core framework only via the global `Shopware` object. Using the concept of destructuring can help to access just specific parts of the framework and maintain readability of your code. Nevertheless you can use static imports in your plugins to import other source files of your plugin or NPM dependencies.

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

* The Shopping Experiences data handling has changed. To get an entity resolved in an element you now need to configure a configfield like this:
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
    
* **Important Change:** Removed module export of `Shopware` and all children

     Before:
     ```
        import Application from 'src/core/shopware';
     ```
  
    After:
    ```
        const Application = Shopware.Application;
    ```
  
* **Important Change:** `context` is now only available in `service`

     Before:
     ```
        const context = Shopware.Application.getContainer('init').contextService;
     ```
  
    After:
    ```
        const context = Shopware.Application.getContainer('service').context;
    ```
  
* **Important Change:** You can use specific helper functions for components with `getComponentHelper()`

     Before:
     ```
        import { mapApiErrors } from 'src/app/service/map-errors.service';
        import { mapState, mapGetters } from 'vuex';
     ```
  
    After:
    ```
        const { mapApiErrors, mapState, mapGetters } = Shopware.Component.getComponentHelper();
  
* **Important Change:** All factories and services are initialized before app starts

   Before:
   ```
      import deDeSnippets from './snippet/de-DE.json';
      import enGBSnippets from './snippet/en-GB.json';
      
      Shopware.Application.addInitializerDecorator('locale', (localeFactory) => {
          localeFactory.extend('de-DE', deDeSnippets);
          localeFactory.extend('en-GB', enGBSnippets);
      
          return localeFactory;
      });
   ```

  After:
  ```
      import deDeSnippets from './snippet/de-DE.json';
      import enGBSnippets from './snippet/en-GB.json';
       
      Shopware.Locale.extend('de-DE', deDeSnippets);
      Shopware.Locale.extend('en-GB', enGBSnippets);
  ```
  
* Component have to be registered before they can be used in the modules

   Before:
   ```
   export default {
        name: 'demo-component',
        ...
   }
   ```
   ```
      import demoComponent from './page/demo-component';
      
      Module.register('demo-module', {
          routes: {
              index: {
                  component: demoComponent,
                  path: 'index',
                  meta: {
                      parentPath: 'sw.demo.index'
                  }
              }
          }
      });
   ```

  After:
  ```
     Shopware.Component.register('demo-component', {
          ...
     });
  ```
  ```
      import './page/demo-component';
            
      Module.register('demo-module', {
        routes: {
            index: {
                component: 'demo-component',
                path: 'index',
                meta: {
                    parentPath: 'sw.demo.index'
                }
            }
        }
      });
  ```
    
* Refactored administration booting process
    * Plugins are now injected asynchronous after initialization of all dependencies
    * Plugins have full access to all functionalities which are used by the `app`
    * The booting of the login is separated from the application booting. Therefore the login is not expandable with plugins anymore.
    
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
        mediaCriteria.addSorting(Criteria.sort('media.fileName', 'ASC'));
        mediaCriteria.addFilter(Criteria.equals('fileName', 'testImage'));
        ```

* Shopping Experience sections.<br>
The Shopping Experiences now have sections to separate the blocks of a page.
Also the change allows it to have different types of sections eg. one with a sidebar. <br><br>
Structure is now Page->**Section**->blocks->slots <br>
To migrate your existing data run `bin/console database:migrate --all Shopware\\` <br><br> See `2019-09-27-breaking-change-cms-sections` for more information

* Context is seperated in App and Api Context

    Before:
    ```js
      Shopware.Context
    ```
  
    After:
    ```js
      Shopware.Context.app // or
      Shopware.Context.api
    ```
  
    Before:
    ```js
      inject: ['context'],
      ...
      this.repository.search(criteria, context)
    ```  
    or
    ```js
      inject: ['apiContext'],
      ...
      this.repository.search(criteria, apiContext)
    ```
  
    After:  
    Now you do not need to inject the context and can use the context directly.
    ```
      this.repository.search(criteria, Shopware.Context.api)
    ```
  
* State was replaced by Vuex state. The old state was renamed to `StateDeprecated`

    Before:
    ```js
      Shopware.State
    ```
  
    After:
    ```js
      Shopware.StateDeprecated
    ```

* Refactored the multiple inheritance of vuejs components and `$super` method with a **breaking change**!

	The syntax for the `$super` call has been changed as follows.

    ```js
    this.$super('relatedMethodName', ...args);
    ```

    You can use `$super` for _computed_ properties and _methods_ to invoke the behaviour of the component you want to extend or override.

    Before:

    ```js
        import template from './test-component.html.twig';

        ComponentFactory.register('test-component', {
            template,

            data() {
                return {
                    _value: '';
                }
            },

            computed: {
                fooBar() {
                    return 'fooBar';
                },
                currentValue: {
                    get() {
                        return this._value;
                    },
                    set(value) {
                        this._value = value;
                    }
                }
            },

            methods: {
                uppercaseCurrentValue() {
                    return this.currentValue.toUpperCase();
                }
            }
        });

        ComponentFactory.extend('extension-1', 'test-component', {
            computed: {
                fooBar() {
                    const prev = this.$super.fooBar();

                    return `${prev}Baz`;
                },
                currentValue: {
                    get() {
                        this.$super.currentValue();

                        return `foo${this._value}`;
                    },
                    set(value) {
                        this.$super.currentValue(value);

                        this._value = `${value}Baz!`;
                    }
                }
            },

            methods: {
                uppercaseCurrentValue() {
                    const prev = this.$super.uppercaseCurrentValue();

                    return prev.reverse();
                }
            }
        });
    ```

    After:

    ```js
    import template from './test-component.html.twig';

    ComponentFactory.register('test-component', {
        template,

        data() {
            return {
                _value: '';
            }
        },

        computed: {
            fooBar() {
                return 'fooBar';
            },
            currentValue: {
                get() {
                    return this._value;
                },
                set(value) {
                    this._value = value;
                }
            }
        },

        methods: {
            uppercaseCurrentValue() {
                return this.currentValue.toUpperCase();
            }
        }
    });

    ComponentFactory.extend('extension-1', 'test-component', {
        computed: {
            fooBar() {
                const prev = this.$super('fooBar');

                return `${prev}Baz`;
            },
            currentValue: {
                get() {
                    this.$super('currentValue.get');

                    return `foo${this._value}`;
                },
                set(value) {
                    this.$super('currentValue.set', value);

                    this._value = `${value}Baz!`;
                }
            }
        },

        methods: {
            uppercaseCurrentValue() {
                const prev = this.$super('uppercaseCurrentValue');

                return prev.reverse();
            }
        }
    });
    ```
  
 * Added new properties to the view in the `sw_sales_channel_detail_content_view` block.
    
    Before:
    ```twig
   {% block sw_sales_channel_detail_content_view %}
       <router-view :salesChannel="salesChannel"
                    :customFieldSets="customFieldSets"
                    :isLoading="isLoading"
                    :key="$route.params.id">
       </router-view>
   {% endblock %}
   ``` 
   
   After:
   ```twig
   {% block sw_sales_channel_detail_content_view %}
       <router-view :salesChannel="salesChannel"
                    :productExport="productExport"
                    :storefrontSalesChannelCriteria="storefrontSalesChannelCriteria"
                    :customFieldSets="customFieldSets"
                    :isLoading="isLoading"
                    :productComparisonAccessUrl="productComparison.productComparisonAccessUrl"
                    :key="$route.params.id"
                    :templateOptions="productComparison.templateOptions"
                    :showTemplateModal="productComparison.showTemplateModal"
                    :templateName="productComparison.templateName"
                    @template-selected="onTemplateSelected"
                    @access-key-changed="generateAccessUrl"
                    @domain-changed="generateAccessUrl"
                    @invalid-file-name="setInvalidFileName(true)"
                    @valid-file-name="setInvalidFileName(false)"
                    @template-modal-close="onTemplateModalClose"
                    @template-modal-confirm="onTemplateModalConfirm">
       </router-view>
    {% endblock %}
   ```

Storefront
----------

**Changes**

* A theme must now implement the `Shopware\Storefront\Framework\ThemeInterface`.
* If your javascript lives in `Resources/storefront/script` you have to explicitly define this path in the `getStorefrontScriptPath()` method of your plugin base class as we have changed the default path to `Resources/dist/storefront/js`.
* Added `extractIdsToUpdate` to `Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteInterface`. `extractIdsToUpdate` must provide the ids of entities which seo urls should be updated based on an EntityWrittenContainerEvent.
* Replace `productUrl(product)` with `seoUrl('frontend.detail.page', {'productId': product.id}) }` and `navigationUrl(navigation)` with `seoUrl('frontend.navigation.page', { 'navigationId': navigation.id })`'
* The JavaScript `CmsSlotReloadPlugin` is no longer used to render the response after paginating a product list. This has been moved to the `ListingPlugin` which can be found in `platform/src/Storefront/Resources/src/script/plugin/listing/listing.plugin.js`.
  * The `ListingPlugin` now handles the pagination as well as the product filter and the new sorting element.
  * The pagination uses a separate JavaScript plugin `listing-sorting.plugin.js`.
* We simplified the implementation of the `\Shopware\Storefront\Framework\Cache\CacheWarmer\CacheRouteWarmer`
    * The class is now an interface instead of an abstract class
    * It is no longer necessary to implement your own `WarmUpMessage` class
    * It is no longer necessary to register your class as message queue handler
    * Removed the `handle` function removed without any replacement
    * The `\Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage` now expects the route name and a parameter list
    * See `\Shopware\Storefront\Framework\Cache\CacheWarmer\Product\ProductRouteWarmer` for detail information.
* We added two new environment variables `SHOPWARE_HTTP_CACHE_ENABLED` and `SHOPWARE_HTTP_DEFAULT_TTL` which have to be defined in your `.env` file
```
SHOPWARE_HTTP_CACHE_ENABLED=1
SHOPWARE_HTTP_DEFAULT_TTL=7200
```
* We supports now the symfony http cache. You have to change the `index.php` of your project as follow:

    Before:
    ```php
    // resolves seo urls and detects storefront sales channels
    $request = $kernel->getContainer()
        ->get(RequestTransformerInterface::class)
        ->transform($request);

    $response = $kernel->handle($request);
    ``` 
    
    After:
    ```php
    // resolves seo urls and detects storefront sales channels
    $request = $kernel->getContainer()
        ->get(RequestTransformerInterface::class)
        ->transform($request);

    $enabled = $kernel->getContainer()->getParameter('shopware.http.cache.enabled');
    if ($enabled) {
        $store = $kernel->getContainer()->get(\Shopware\Storefront\Framework\Cache\CacheStore::class);
        $kernel = new \Symfony\Component\HttpKernel\HttpCache\HttpCache($kernel, $store, null, ['debug' => $debug]);
    }

    $response = $kernel->handle($request);
    ```
* We moved the administration sources from Resources/administration to Resources/app/administration. This also applies to the expected plugin admin extensions. 

* CSRF implementation
    * Every `POST` method needs to append a CSRF token now
    * CSRF tokens can be generated in twig or via ajax, if configured. Here is a small twig example for a typical form:
    ```twig
      <form name="ExampleForm" method="post" action="{{ path("example.route") }}" data-form-csrf-handler="true">
          <!-- some form fields -->
        
          {{ sw_csrf('example.route') }}
        
      </form>
    ```
    * Important: The CSRF function needs the route name as parameter, because a token is only valid for a specific route in twig mode
    * To prevent a CSRF check in a controller action you can set `"csrf_protected"=false` to the `defaults` in your route annotation:
    ```php
       /**
         * @Route("/example/route", name="example.route", defaults={"csrf_protected"=false}, methods={"POST"})
        */
    ```
* Removed abandoned TwigExtensions in favor of  Twig Core Extra extensions
    * Use `u.wordwrap` and `u.truncate` instead of the `wordwrap` and `truncate` filter.
    * Use the `format_date` or `format_datetime` filter instead of the `localizeddate` filter
    * Take a look here for more information: https://github.com/twigphp/Twig-extensions
* Removed the contact and newsletter page
    * If you used the template `platform/src/Storefront/Resources/views/storefront/page/newsletter/index.html.twig` or `platform/src/Storefront/Resources/views/storefront/page/contact/index.html.twig` your changes will not work anymore. You now find them here: `platform/src/Storefront/Resources/views/storefront/element/cms-element-form`.
    * The templates are part of the new `form cms element` that can be used in layouts. Therefore all changes in these templates are applied wherever you use the cms element.
    * We added two `default shop page layouts` for the `contact` and `newsletter form` in the `shopping experiences` where the cms form element is used.
    * These layouts have to be assigned in the `settings` under `basic information` for `contact pages` and `newsletter pages`.
    * The assigned layout for `contact pages` is used in the footer `platform/src/Storefront/Resources/views/storefront/layout/footer/footer.html.twig` as modal.
* We split the `Storefront/Resources/views/storefront/layout/navigation/offcanvas/navigation.html.twig` template into smaller templates. If you have extended this template you should check if the blocks are still correctly overwritten. If this is not the case, you have to extend the smaller template file into which the block was moved. 
* The data format of the `lineItem.payload.options` has changed. Now there is a simple array per element with `option` and `group`. It contains the translated names of the entities. If you have changed the template `storefront/page/checkout/checkout-item.html.twig` you have to change the following: 
    Before:
    ```twig
    {% block page_checkout_item_info_variants %}
        {% if lineItem.payload.options|length >= 1 %}
            <div class="cart-item-variants">
                {% for option in lineItem.payload.options %}
                    <div class="cart-item-variants-properties">
                         <div class="cart-item-variants-properties-name">{{ option.group.translated.name }}:</div>
                         <div class="cart-item-variants-properties-value">{{ option.translated.name }}</div>
                    </div>
                {% endfor %}
            </div>
        {% endif %}
    {% endblock %}    
    ``` 

    After:
    ```twig
    {% block page_checkout_item_info_variants %}
        {% if lineItem.payload.options|length >= 1 %}
            <div class="cart-item-variants">
                {% for option in lineItem.payload.options %}
                    <div class="cart-item-variants-properties">
                        <div class="cart-item-variants-properties-name">{{ option.group }}:</div>
                        <div class="cart-item-variants-properties-value">{{ option.option }}</div>
                    </div>
                {% endfor %}
            </div>
        {% endif %}
    {% endblock %}    
    ``` 
  * The following blocks moved from the `storefront/element/cms-element-product-listing.html.twig` template into the new template `storefront/component/product/listing.html.twig`. If you have overwritten one of the following blocks, you must now extend the `storefront/component/product/listing.html.twig` template instead of the `storefront/element/cms-element-product-listing.html.twig` template 
    * `element_product_listing_wrapper_content`
    * `element_product_listing_pagination_nav_actions`
    * `element_product_listing_pagination_nav_top`
    * `element_product_listing_sorting`
    * `element_product_listing_row`
    * `element_product_listing_col`
    * `element_product_listing_box`
    * `element_product_listing_col_empty`
    * `element_product_listing_col_empty_alert`
    * `element_product_listing_pagination_nav_bottom`
  * The `storefront/component/listing/filter-panel.html.twig` component requires now a provided `sidebar (bool)` parameter. Please provide this parameter in the `sw_include` tag:
    ```twig
        {% sw_include '@Storefront/storefront/component/listing/filter-panel.html.twig' with {
            listing: listing,
            sidebar: true
        } %}
    ```
    
Elasticsearch
-------------

**Changes**

* The env variables `SHOPWARE_SES_*` were renamed to `SHOPWARE_ES_*`.
* If you used one of the elastic search parameter in your services.xml you have to change it as follow:
    Before:
    ```
      <service ....>
         <argument>%shopware.ses.enabled%</argument>
         <argument>%shopware.ses.indexing.enabled%</argument>
         <argument>%shopware.ses.index_prefix%</argument>
      </service>       
    ```

    After:
    ```
      <service ....>
         <argument>%elasticsearch.enabled%</argument>
         <argument>%elasticsearch.indexing_enabled%</argument>
         <argument>%elasticsearch.index_prefix%</argument>
      </service>    
    ```
* The extensions are now saved at the top level of the entities.
    * Now you have to change the ElasticsearchDefinition::getMapping for external resources.
   
        Before:
        ```
            'extensions' => [
                'type' => 'nested
                'properties' => [
                    'extensionsField' => $this->mapper->mapField($definition, $definition->getField('extensionsField'), $context)
                ]
            ]
        ```
      
       After:
      ```
            'extensionsField' => $this->mapper->mapField($definition, $definition->getField('extensionsField'), $context)
      ```     
    * And you have to reindex.

