---
title: Deprecation strategy
date: 2021-01-21
area: core
tags: [deprecation, feature-flags, workflow]
---

## Superseded by [Feature flags for major versions](../2022-01-20-feature-flags-for-major-versions.md)

## Context

Define a strategy for deprecations.

## Decision

### Dogma
* Don't do changes without feature-flags (only exception is a bugfix)
* Don't break things without an alternative
* Don't break things in a minor release
* Annotate upcoming breaks as soon as possible
* Test all new implementations and changes
* Be expressive and very verbose on instructions in your inline feature flag comments
* There is a world outside with developers that use our public code

### Synopsys

As we decided to work on the trunk-based development from now on, there are different kinds of cases we need to consider while implementing changes to not cause any breaks while developing for future features.
The main difference we have to take in account is if we break current behaviour with our changes or not.
For this difference, we have 4 different cases:
* Minor Changes which don't cause any breaks or deprecations
* Minor Changes which cause deprecations
* Minor Changes as part of a major feature which don't cause any breaks
* Major changes which cause breaks

For a quick overview, this is how you have to deal with the different cases. 
Concrete Examples and further explanation follow below.

#### Only Minor Changes (no breaks)
Features and changes tend to be released in a minor release. Don't cause breaks. Simple additions, refactorings, etc
* Put all your changes behind a feature flag, to be sure that nothing you have changed is called while developing is in progress.
* When Development is completed, remove the feature flag and all the old code that is not used anymore
* Detailed description here [Detailed Rules](#detailed-rules)

#### Only Minor Changes (with deprecating code)
Features and Changes tend to be released in a minor release and are developed in a backward compatible manner, but deprecate old code. For example, a class is replaced by a new one.
* Put all your changes behind a feature flag, to be sure that nothing you have changed is called while developing is in progress.
* When Development is completed, remove the feature flag and all the old code that is not used anymore
* Mark old code as deprecated and make sure it is not called anywhere else
* Make sure everything you removed has a working alternative implemented.
* Annotate everything in a manner that the removal of the deprecated code will be a no-brainer on the next major release
* Detailed description here [Detailed Rules](#detailed-rules)

#### Major Changes (Breaks)
Parts of a major feature or refactoring which breaks current behaviour. Removal of classes, methods or properties, change of signatures, business logic changes...
* Put all your changes behind a feature flag, to be sure that nothing you have changed is called while developing is in progress.
* When Development is completed, remove the feature flag and all the old code that is not used anymore
* Mark old code as deprecated and make sure it is not called anywhere else
* Make sure everything you removed has a working alternative implemented.
* Annotate everything in a manner that the removal of the deprecated code will be a no-brainer on the next major release
* The only difference between the case above is that you have to take care of the fact that the whole old behaviour needs to be fully functional until the next major.
* Write specific tests for the major flag which tests the new behaviour.
* Detailed description here [Detailed Rules](#detailed-rules)

## Summary Deprecations:

  * The old code [^3] (class/method/property/event...) will be annotated with @feature-deprecated or @major-deprecated depending on the fact if this is a breaking change or not.
  * There will be parts of the changes which cause breaks and parts which don't cause breaks. Following, we will call these parts "breaking code" [^4] and "non breaking code" [^5]
  * The breaking code has to be hidden behind a feature flag (major-flag [^6]) until the next major release.
  * The non breaking code only needs to be hidden behind a feature flag (minor-flag [^7]), while developing. As soon as the feature or change is tested, quality ensured and approved the flag can be removed.
  * The breaking code will only be used, if the corresponding major-flag is active.
  * The old code is not used anymore by our code basis [^8] if the corresponding feature flag [^9] is active.
  * The old code should not be called if the feature flag is active to prevent accidentally kept dependencies. 
  * Both ways have to be fully tested until the feature flag is removed.

## Consequences

### Conclusion
When the code-basis is changed, the old way has to be fully functional until the release of the next major version. Even soft-breaks [^10] have to be avoided if possible. Security updates and serious bugfixes should be the only reasons for soft-breaks in a minor release. 
Deprecated code and major-deprecated code will not be called anywhere in the code-basis while the major-flags are not active.

As an Example the removal of the class `MySampleClass` and the replacement with `MyNewSampleClass`

Version 6.3.3.0 - While developing
The new class `MyNewSampleClass` is implemented and marked as `@internal (flag:FEATURE_NEXT_22222)`.
The old class `MySampleClass` is annotated  as `@feature-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222)`.
The code which calls the old class `MySampleClass` gets a feature switch which is annotated as `@major-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222)` 

Version 6.3.4.0 - Feature Release
The new class `MyNewSampleClass` is implemented and marked as `@internal`.
The annotation of the old class `MySampleClass` is changed to `@deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222)`.

Version 6.4.0.0 - Major Release
The new class `MyNewSampleClass` will now be used regardless of feature flags and the `@internal` annotation will be removed.
The old class `MySampleClass` will be removed.

### Examples

In these examples we assume the minor flag is called `FEATURE_NEXT_11111` and the major flag is called `FEATURE_NEXT_22222`.

#### Startup examples
These are some Startup examples. We have some more complex examples below [Complex examples](Complex examples) if you want to know more for a special case.
In general, it is recommended to read the startup examples and come back to the complex examples if you face a specific question.

##### Changelog
Often there are changes in a major feature which will be released immediately after the removal of the minor flag, because they don't break anything, and other changes which will not be used until the next major release.
In that case, 2 Changelogs should be provided.  
One with the changes for the minor, marked with the minor flag.
One for the major release with the changes behind the major flag, which will be marked with the major flag.
In this way, only the minor changes will be published with the minor release and the major changes will be published with the major release.

Changelog minor
```md
---
title: I have done something
issue: NEXT-11111
flag: FEATURE_NEXT_11111
---
# Core
*  Added a property to SampleClass
``` 

Changelog major
```md
---
title: I have done something serious
issue: NEXT-22222
flag: FEATURE_NEXT_22222
---
# Core
*  Removed the property `something` in SampleClass
```

##### Services
Services are somehow special because they are always initiated in the container. So it is ok to change anything in the constructor of a service because it always has to be used from the container instance.
_If someone initiate a service directly with `new ServiceController()` we don't provide compatibility_. 

###### Declare a new Service
When a new service is implemented, the tag `shopware.feature` with the minor-flag has to be added in the dependencyInjection.xml.
The new service should be usable as soon as the development is finished to allow plugin developers to implement new services early.
If it is not intended to allow the use beforehand, you can also use the major flag to make the service unavailable until the next major.
service.xml
```xml
        <service id="Shopware\Core\Content\MyTest\Service\MyNewTestClass" public="true">
            <argument type="service" id="Shopware\Core\System\SystemConfig\SystemConfigService"/>
            <tag name="shopware.feature" flag="FEATURE_NEXT_11111"/>
        </service>
```

###### Exchange a service with a new one
If you want to exchange an old service with a new one, you act for the new service like above.
If the old service is not used anywhere right now, you can deprecate it with the symfony tag.
On feature release, the service will be deprecated with the symfony tag:
```xml
        <!-- feature-deprecated flag:FEATURE_NEXT_22222 deprecate service on feature release -->
        <service id="Shopware\Core\Content\MyTest\Service\MyTestClass" public="true">
            <argument type="service" id="Shopware\Core\System\SystemConfig\SystemConfigService"/>
            <deprecated>tag:v6.4.0: The "%alias_id%" service is deprecated and will be removed in 6.4.0. Use "%Shopware\Core\Content\MyTest\Service\MyTestClass%" instead<deprecated/>
        </service>
```
If it is still used, but marked as major-deprecated, you can use the tag-type "deprecated" with the major flag.
This will cause an error if this service is still used while the major flag is active.
```xml
        <!-- feature-deprecated flag:FEATURE_NEXT_22222 deprecate service on feature release -->
        <service id="Shopware\Core\Content\MyTest\Service\MyTestClass" public="true">
            <argument type="service" id="Shopware\Core\System\SystemConfig\SystemConfigService"/>
            <tag name="deprecated" flag="FEATURE_NEXT_22222" version="tag:v6.4.0"/>
        </service>
```

##### Adding many new Services
If you are implementing a new Feature with many new Services, you could decide to create a whole new dependencyInjection.xml and load it depending on the feature Flag.
This way you don't have to mess with every single new service.

* Create a new xml. As an Example `src/Core/Framework/DependencyInjection/nextLevelShopping.xml`
* Add the new xml to the relevant bundle.php. In this case, it would be the Framework.php

```php
class Framework extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
        $loader->load('acl.xml');
        $loader->load('api.xml');
        $loader->load('app.xml');
        $loader->load('custom-field.xml');
        $loader->load('data-abstraction-layer.xml');
        if (Feature::isActive('FEATURE_NEXT_22222')) {
            $loader->load('nextLevelShopping.xml');
        }
   }
}
```

##### Unused classes
Classes which should not be used anymore should marked as deprecated with FEATURE:triggerDeprecated.
We cannot simply remove it directly because a class can always be initiated in a plugin.

PHP
```php
/**
 * @deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) MyTestClass will be removed in 6.4.0 use MyNewTestClass instead
 */
class MyTestClass
{
    public function __construct(SystemConfigService $firstArgument, Something $secondArgument): MyTestClass
    {
        FEATURE::triggerDeprecated('FEATURE_NEXT_22222', 'v6.3.4.0', 'v6.4.0', 'Use %s instead', 'MyNewTestClass');
    }

}
```

##### UnitTests
If a test case changes due to a code change, the whole case should be duplicated.
This will make it much easier to differ the old and the new tests and remove the old tests on major release without breaking anything.
The tests for the old behaviour should be skipped if the feature flag is activated, and the tests of the new behaviour should be skipped if the feature flag is inactive.
The old tests have to be annotated as @group legacy.

```php
/**
 * @major-deprecated (flag:FEATURE_NEXT_22222) test will be removed in 6.4.0
 * @group legacy
 */
public function testOldWorkflow(): void
{
    Feature::skipTestIfActive('FEATURE_NEXT_22222', $this);
}

public function testNewWorkflow(): void
{
    Feature::skipTestIfInActive('FEATURE_NEXT_22222', $this);
}

```

##### Add an argument to a public method
If you want to add an argument to a public method, you have to add the new argument as a comment and use func_get_args to get this argument if provided.

```php
    /**
     * @feature-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) - Parameter $precision will be mandatory in future implementation 
     */
    public function calculate(ProductEntity $product, Context $context /*, int $precision */): Product
    {
        if (Feature::isActive('FEATURE_NEXT_22222')) {
            if (\func_num_args() === 3) {
                $precision = func_get_arg(2);
                //Do new calculation
            } else {
                throw new InvalidArgumentException('Argument 3 $precision is required with feature FEATURE_NEXT_22222');
            }
        } else {
            //Do old calculation
        }
    }

```

##### Change direct Service Call to PageLoader and route call

###### Original
```php
class Controller
{
    private $productService;
    
    public function index($id) 
    {
        $product = $this->productService->getProductForStorefront($id);
        
        $page = new ProductPage($product);
        
        $this->render($page);
    }
}

class ProductService
{
    public function getProduct(string $id)	
    {
        // do other required stuff
        return $this->productRepository->search(new Criteria([$id]))->first();
    }
    
    public function getProductForStorefront(string $id)	
    {
        // do other required stuff
        $product = $this->productRepository->search(new Criteria([$id]))->first();
        $this->enrichForStoreFront($product);
        return $product;
    }
}

```

###### while development
```php
class Controller
{
    /** @internal (flag:FEATURE_NEXT_11111) */
    private $loader;
    
    /** @deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) $productService will be removed, use $loader instead */
    private $productService;
    
    public function index($id) 
    {
        /** @major-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) productService will be removed. Keep if-branch */
        if (Feature::isActive('FEATURE_NEXT_22222')) { 
            $page = $this->loader->load();
        } else {
            $product = $this->productService->getProductForStorefront($id);
            $page = new ProductPage($product);
        }
        $this->render($page);
    }
}

class ProductService
{
    public function getProduct(string $id)	
    {
        // do other required stuff
        return $this->productRepository->search(new Criteria([$id]))->first();
    }
    
    /**
     * @deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) getProductForStorefront will be removed. Use ProductRoute->load() instead
     */
    public function getProductForStorefront(string $id)	
    {
        // do other required stuff
        $product = $this->productRepository->search(new Criteria([$id]))->first();
        $this->enrichForStoreFront($product);
        return $product;
    }
}

/**
 * @internal (flag:FEATURE_NEXT_11111) remove when development is finished. New Loader can be used as soon as possible
 */
class ProductLoader
{
    private $productRoute;
    
    public function load()
    {
        $product = $this->productRoute->load($id);
        $page = new ProductPage($product);
        return $page;
    }
}

/**
 * @internal (flag:FEATURE_NEXT_11111) remove when development is finished. New Route can be used as soon as possible
 */
class ProductRoute
{
    public function load($id)	
    {
        // do other required stuff
        $product = $this->productService->getProduct($id);
        $salesChannelProduct = $this->enrichSalesChannelThings($product);
        return $salesChannelProduct;
    }
}
```

###### Development finished: merged into trunk and remove feature flag
```php
class Controller
{
    private $loader;
    
    /** @deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) $productService will be removed, use $loader instead */
    private $productService;
    
    public function index($id) 
    {
        /** @major-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) productService will be removed. Keep if-branch */
        if (Feature::isActive('FEATURE_NEXT_22222')) { 
            $page = $this->loader->load();
        } else {
            $product = $this->productService->getProductForStorefront($id);
            $page = new ProductPage($product);
        }
        $this->render($page);
    }
}

class ProductService
{
    public function getProduct(string $id)	
    {
        // do other required stuff
        return $this->productRepository->search(new Criteria([$id]))->first();
    }
    
    /**
     * @deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) getProductForStorefront will be removed. Use ProductRoute->load() instead
     */
    public function getProductForStorefront(string $id)	
    {
        // do other required stuff
        $product = $this->productRepository->search(new Criteria([$id]))->first();
        $this->enrichForStoreFront($product);
        return $product;
    }
}

class ProductLoader
{
    private $productRoute;
    
    public function load()
    {
        $product = $this->productRoute->load($id);
        $page = new ProductPage($product);
        return $page;
    }
}

class ProductRoute
{
    public function load($id)	
    {
        // do other required stuff
        $product = $this->productService->getProduct($id);
        $salesChannelProduct = $this->enrichSalesChannelThings($product);
        return $salesChannelProduct;
    }
}
```

###### Major Release: v6.4.0 ####
```php
class Controller
{
    private $loader;
    
    public function index($id) 
    {
        $page = $this->loader->load();
        $this->render($page);
    }
}

class ProductService
{
    public function getProduct(string $id)	
    {
        // do other required stuff
        return $this->productRepository->search(new Criteria([$id]))->first();
    }
}

class ProductLoader
{
    private $productRoute;
    
    public function load()
    {
        $product = $this->productRoute->load($id);
        $page = new ProductPage($product);
        return $page;
    }
}

class ProductRoute
{
    public function load($id)	
    {
        // do other required stuff
        $product = $this->productService->getProduct($id);
        $salesChannelProduct = $this->enrichSalesChannelThings($product);
        return $salesChannelProduct;
    }
}
```

### Complex examples
You don't have to read all these examples in one take. It's most likely they will confuse you more than help you if you don't have a concrete case in mind.
Come to this section if you stumble over a case where you don't exactly know that to do.
If you don't find your answer here, don't be silent. Call us out in slack for that we are aware of cases that have to be stated here.

#### Rename or removal of a property
Should a property of a class be removed, it will be annotated as deprecated and will only be used in code which is also deprecated. Should a property be renamed, it will be deprecated instead and we implement a new property with the new name.

##### While Developing:

###### Original class:
```php
class MyTestClass
{
    public $oldPublicProperty;

    private $oldPrivateProperty;

    public function main(SystemConfigService $firstArgument, Something $secondArgument)
    {
        $this->setOldPublicProperty($secondArgument);
    }

    public function getOldPrivateProperty()
    {
        return $this->oldPrivateProperty;
    }
    
    private function setOldPrivateProperty($oldPrivateProperty): void 
    {
        $this->oldPrivateProperty = $oldPrivateProperty;
    }

    public function getOldPublicProperty(){
        $this->setOldPrivateProperty($this->oldPublicProperty / 2);
        return $this->oldPublicProperty;
    }
    
    private function setOldPublicProperty($oldPublicProperty): void 
    {
        $this->setOldPrivateProperty($oldPublicProperty);
        $this->oldPublicProperty = $oldPublicProperty;
    }

    public function getSomething(): int 
    {
        return 1;
    }
}

// The calling class
class MyTestService
{
    public function __construct(MyTestClass $myTestClass): MyTestService 
    {
        $myTestClass->getOldPublicProperty();
    }
}
```

###### New Property implemented:

```php
class MyTestClass
{
    /**
     * @deprecated (flag:FEATURE_NEXT_22222) property will be removed. Use $newPublicProperty instead
     * adr-explain: deprecated, because this property is public and could be used anyhwere. 
     */
    public $oldPublicProperty;
    
    /**
     * @internal (flag:FEATURE_NEXT_11111)
     */
    public $newPublicProperty;

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_11111) property will be removed. Use $newPrivateProperty instead
     * adr-explain: minor flag used, because this property is private and can be removed as soon as the development is finished
     */
    private $oldPrivateProperty;

    /**
     * @internal (flag:FEATURE_NEXT_11111)
     * adr-explain: minor flag used, because this new property can be used as soon as the development is finished 
     */
    private $newPrivateProperty;

    public function main(SystemConfigService $firstArgument, Something $secondArgument): MyTestClass
    {
        /** @feature-deprecated (flag:FEATURE_NEXT_11111) setOldPublicProperty will be removed on feature release. Remove this call also */
        if (!Feature::isActive('FEATURE_NEXT_11111')) {
            $this->setOldPublicProperty($secondArgument);
        } else {
            $this->setNewPublicProperty($secondArgument * 2);
        }
    }

    /**
     * @internal (flag:FEATURE_NEXT_11111)
     */
    public function getNewPrivateProperty(){
        if (!Feature::isActive('FEATURE_NEXT_11111')) {
            throw new FeatureNotActiveException('FEATURE_NEXT_11111');
        }

        return $this->newPrivateProperty;
    }

    /**
     * @internal (flag:FEATURE_NEXT_11111) 
     */
    public function setNewPrivateProperty($newPrivateProperty): void 
    {
         if (!Feature::isActive('FEATURE_NEXT_11111')) {
            throw new FeatureNotActiveException('FEATURE_NEXT_11111');
         }

        $this->newPrivateProperty = $newPrivateProperty;
    }

    /**
     * @internal (flag:FEATURE_NEXT_11111)
     */
    public function getNewPublicProperty(){
        if (!Feature::isActive('FEATURE_NEXT_11111')) {
            throw new FeatureNotActiveException('FEATURE_NEXT_11111');
        }

        return $this->newPublicProperty;
    }

    /**
     * @internal (flag:FEATURE_NEXT_11111)
     */    
    public function setNewPublicProperty($newPublicProperty): void 
    {
        if (!Feature::isActive('FEATURE_NEXT_11111')) {
            throw new FeatureNotActiveException('FEATURE_NEXT_11111');
        }

        $this->newPublicProperty = $newPublicProperty;
    }

    /**
     * @deprecated (flag:FEATURE_NEXT_22222) getter will be removed. Use getNewPrivateProperty instead
     * adr-explain: @deprecated because this is a public method which can be called from plugins etc. 
     *              Attention! The used property was changed to the newPrivateProperty to make it possible to remove
     *              the old one as soon as possible.
     */
    public function getOldPrivateProperty()
    {
        return $this->newPrivateProperty / 2;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_11111) setter will be removed. Use setNewPrivateProperty instead
     * adr-explain: @feature-deprecated because this is a private method which can't be called from outside
     */    
    private function setOldPrivateProperty($oldPrivateProperty): void 
    {
        $this->oldPrivateProperty = $oldPrivateProperty;
    }

    /**
     * @deprecated (flag:FEATURE_NEXT_22222) setter will be removed. Use setNewPublicProperty instead
     * adr-explain: @deprecated because this is a public method which can be called from plugins etc.
     */    
    public function getOldPublicProperty(){
        /** @feature-deprecated (flag:FEATURE_NEXT_11111) setOldPrivateProperty will be removed on feature release. Keep the else branch*/
        if (!FEATURE::isActive('FEATURE_NEXT_11111')) {
            $this->setOldPrivateProperty($this->oldPublicProperty / 2);
        } else {
            $this->setNewPrivateProperty($this->oldPublicProperty * 2);
        }
        
        return $this->oldPublicProperty;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_11111) setter will be removed. Use setNewPublicProperty instead
     * adr-explain: @feature-deprecated because this is a private method which can't be called from outside 
     */        
    private function setOldPublicProperty($oldPublicProperty): void 
    {
        $this->oldPublicProperty = $oldPublicProperty;
    }

}

// The calling class
class MyTestService
{
    public function __construct(MyTestClass $myTestClass): MyTestService 
    {
        /** @major-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) keep the if branch remove the else branch */
        if (FEATURE::isActive('FEATURE_NEXT_22222')) {
            $myTestClass->getNewPublicProperty();
        } else {
            $myTestClass->getOldPublicProperty();
        } 
    }
}
```

##### Minor feature release: (Development finished)

```php
class MyTestClass
{
    /**
     * @deprecated (flag:FEATURE_NEXT_22222) property will be removed. Use $newPublicProperty instead
     */
    public $oldPublicProperty;
    
    /**
     * adr-explain: @internal removed because it was tagged with minor feature flag
     */
    public $newPublicProperty;

    /**
     * adr-explain: $oldPrivateProperty was removed. This commentblock is only here for this adr example. Remove the property completly
     */

    /**
     * adr-explain: @internal removed because it was tagged with minor feature flag
     */
    private $newPrivateProperty;

    public function main(SystemConfigService $firstArgument, Something $secondArgument): MyTestClass
    {
        /** adr-explain: old call to private method removed because it was tagged with the minor feature flag */
        $this->setNewPublicProperty($secondArgument * 2);
    }

    /**
     * adr-explain: @internal removed because it was tagged with minor feature flag
     */
    public function getNewPrivateProperty(){
        /** adr-explain: Exception removed because it was tagged with the minor feature flag */

        return $this->newPrivateProperty;
    }

    /**
     * adr-explain: @internal removed because it was tagged with minor feature flag
     */
    public function setNewPrivateProperty($newPrivateProperty): void 
    {
        $this->newPrivateProperty = $newPrivateProperty;
    }

    /**
     * adr-explain: @internal removed because it was tagged with minor feature flag
     */
    public function getNewPublicProperty(){
        return $this->newPublicProperty;
    }

    /**
     * adr-explain: @internal removed because it was tagged with minor feature flag
     */    
    public function setNewPublicProperty($newPublicProperty): void 
    {
        $this->newPublicProperty = $newPublicProperty;
    }

    /**
     * @deprecated (flag:FEATURE_NEXT_22222) getter will be removed. Use getNewPrivateProperty instead
     * adr-explain: @deprecated because this is a public method which can be called from plugins etc. 
     *              Attention! The used property was changed to the newPrivateProperty to make it possible to remove
     *              the old one as soon as possible.
     */
    public function getOldPrivateProperty()
    {
        FEATURE::triggerDeprecated('FEATURE_NEXT_22222', 'v6.3.4.0', 'v6.4.0', 'Method getOldPrivateProperty will be removed, use getNewPrivateProberty instead');
        return $this->newPrivateProperty / 2;
    }

    /**
     * adr-explain: the method setOldPrivateProperty was removed because this is a private method tagged with the minor flag
     */    

    /**
     * @deprecated (flag:FEATURE_NEXT_22222) setter will be removed. Use setNewPublicProperty instead
     * adr-explain: @deprecated because this is a public method which can be called from plugins etc.
     */    
    public function getOldPublicProperty(){

        FEATURE::triggerDeprecated('FEATURE_NEXT_22222', 'v6.3.4.0', 'v6.4.0', 'Method getOldPrivateProperty will be removed, use getNewPrivateProberty instead');
        $this->setNewPrivateProperty($this->oldPublicProperty * 2);
        
        return $this->oldPublicProperty;
    }
}

// The calling class
class MyTestService
{
    public function __construct(MyTestClass $myTestClass): MyTestService 
    {
        /** @major-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) keep the if branch, remove the old branch */
        if (FEATURE::isActive('FEATURE_NEXT_22222')) {
            $myTestClass->getNewPublicProperty();
        } else {
            $myTestClass->getOldPublicProperty();
        } 
    }
}
```

##### Major release:

```php
class MyTestClass
{
    /**
     * adr-explain: $oldPublicProperty was removed. This commentblock is only here for this adr example. Remove the property completly
     */
    
    public $newPublicProperty;

    private $newPrivateProperty;

    public function main(SystemConfigService $firstArgument, Something $secondArgument): MyTestClass
    {
        $this->setNewPublicProperty($secondArgument * 2);
    }

    public function getNewPrivateProperty(){
        return $this->newPrivateProperty;
    }

    public function setNewPrivateProperty($newPrivateProperty): void 
    {
        $this->newPrivateProperty = $newPrivateProperty;
    }

    public function getNewPublicProperty(){
        return $this->newPublicProperty;
    }

    public function setNewPublicProperty($newPublicProperty): void 
    {
        $this->newPublicProperty = $newPublicProperty;
    }

    /**
     * adr-explain: method getOldPrivateProperty was removed because it was major-deprecated. This commentblock is only here for this adr example. Just remove the method and the annotations.
     */
}

// The calling class
class MyTestService
{
    public function __construct(MyTestClass $myTestClass): MyTestService 
    {
        /** adr-explain: old call to deprecated method removed because it was tagged with the major feature flag */
        $myTestClass->getNewPublicProperty();
    }
}
```

##### Change return value of method
There are to ways to do this. 
If you can be sure that no caller of the method typehinted the return value, you could remove the return type.
If you cannot be sure (and with public methods, you barely can be sure), you have to add a new method.

###### New Method - while development:
```php
class MyTestClass
{
    /**
     * @deprecated (flag:FEATURE_NEXT_22222) method will be removed. Use getSomewhat instead
     */ 
    public  function getSomething(): int 
    {
        FEATURE::triggerDeprecated('FEATURE_NEXT_22222', 'v6.3.4.0', 'v6.4.0', 'Method getSomething will be removed use getSomewhat instead. The Return value of new Method is string instead of int');
        return 1;
    }

    /**
     * @internal (flag:FEATURE_NEXT_11111) new returnType for getSomething. 
     */ 
    public  function getSomewhat(): string 
    {
        return new Somewhat(1);
    }
}

class MyTestCallerClass
{
    public  function main(MyTestClass $testClass): void 
    {
        /** @feature-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) getSomething will be removed in v6.4.0 keep if-branch on feature release */
        if (Feature::isActive('FEATURE_NEXT_22222')) {
            $this->ensureInt(intval($testClass->getSomewhat()));
        } else {
            $this->ensureInt($testClass->getSomething());
        }
        
    }
}
```

###### New Method - After major release:
```php
class MyTestClass
{
    public  function getSomewhat(): string 
    {
        return '1';
    }
}
```

###### Remove Typehint - while development:
You have to be really sure that there is no call that will break if the return type is not defined

```php
class MyTestClass
{
    /**
     * @deprecated (flag:FEATURE_NEXT_22222) method will be removed. Use getSomewhat instead
     * @return int|string
     */ 
    public  function getSomething() 
    {
        if (Feature::isActive('FEATURE_NEXT_11111')) {
            return 1;
        } else {
            return '1';
        }
    }
}

class MyTestCallerClass
{
    public  function main(MyTestClass $testClass): void 
    {
        /** @feature-deprecated (flag:FEATURE_NEXT_11111) returntype of getSomething will be string. Keep the if-branch */
        if (Feature::isActive('FEATURE_NEXT_11111')) {
            $this->ensureInt(intval($testClass->getSomething()));
        } else {
            $this->ensureInt($testClass->getSomething());
        }
        
        
    }
}
```

###### Remove Typehint - After major release:
```php
class MyTestCallerClass
{
    public  function main(MyTestClass $testClass): void 
    {
        $this->ensureInt(intval($testClass->getSomething()));  
    }
}
```

##### Exchange an argument in service or constructor
If an argument of a class constructor or service should be exchanged, the new argument is added and the old one is set to "nullable" if necessary.
If an argument of a service is removed or exchanged, it has to be annotated in the dependency-xml as well.
If an argument is added, which is not available without the feature, it has to be marked as `on-invalid="null"`. 
Also, it has to be annotated as @internal in the dependency-xml as well and the comment should explicitly explain that the `on-invalid="null"` should be removed.

PHP class
```php
class MyTestClass
{
    /**
     * @major-deprecated (flag:FEATURE_NEXT_22222) $secondArgument will be removed and $thirdAgrument will be mandatory (remove the `?`)
     */
    public function __construct(SystemConfigService $firstArgument, ?Something $secondArgument, ?Somewhat $thirdArgument): MyTestClass
    {
    
    }

}
```

service.xml
```xml
        <!-- major-deprecated flag:FEATURE_NEXT_22222 deprecate service on feature release -->
        <service id="Shopware\Core\Content\MyTest\Service\MyTestClass" public="true">
            <argument type="service" id="Shopware\Core\System\SystemConfig\SystemConfigService"/>
            <!-- major-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) remove argument 'Something' on feature release -->
            <argument type="service" id="Shopware\Core\System\SystemConfig\Something" on-invalid="null" />
            <!-- @internal tag:v6.4.0 (flag:FEATURE_NEXT_22222) remove on-invalid=null on feature release -->
            <argument type="service" id="Shopware\Core\System\SystemConfig\Somewhat" on-invalid="null" />
        </service>
```

##### Change/Removal/Exchange of interfaces

If an interface should be changed in any compatible way, instead you should implement an abstract class.
the abstract class implements the interface until the next major.
This only applies to compatible interface changes. If you have to break the interface, see below.

In this example, the MyTestClass implements MyTestInterface and other classes typehinted MyTestClass with this interface, so you have to make sure that the interface is still present.
###### Original Class
```php
class MyTestClass implements MyTestInterface
{

}

class MyTestCaller
{
    public function doSomething(MyTestInterface $myTest): void
    {
        //
    }
}
```

###### Original Interface
```php
interface MyTestInterface
{
    public function anyMethod(string $name): void;
}
```

###### Deprecated Interface
```php
/**
 * @deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) interface MyTestInterface will be removed in v6.4.0 
 */
interface MyTestInterface
{
    public function anyMethod(string $name): void;
}
```

###### New Abstract class
```php
/**
 * @internal (flag:FEATURE_NEXT_11111) interface MyTestInterface will be removed in v6.4.0 
 */
abstract class MyTestAbstractClass implements MyTestInterface
{
    abstract public function anyMethod(string $name): void;
    abstract public function anyAdditionalMethod(): void; 
}

class MyTestClass extends MyTestAbstractClass 
{
    public function anyMethod(string $name): void
    {
        echo "something old " . $name;
    }
    
    public function anyAdditionalMethod(): void
    {
        echo "something new";
    }
}
```

###### On Minor release
```php
abstract class MyTestAbstractClass implements MyTestInterface
{
    abstract public function anyMethod(string $name): void;
    abstract public function anyAdditionalMethod(): void; 
}
```

###### Removal on Major release
```php
abstract class MyTestAbstractClass
{
    abstract public function anyMethod(string $name): void;
    abstract public function anyAdditionalMethod(): void; 
}

class MyTestClass extends MyTestAbstractClass 
{
    public function anyMethod(string $name): void
    {
        echo "something old " . $name;
    }
    
    public function anyAdditionalMethod(): void
    {
        echo "something new";
    }
}
```

### Detailed Rules
#### Only Minor Changes (no breaks)
Features and changes tend to be released in a minor release. Don't cause breaks. Simple additions, refactorings, etc
* While developing
    * New Code
        * The new code should be hidden behind a feature flag and be annotated with @internal (flag:FEATURE_NEXT_11111) for new code public API [^1].
        * Add new tests for the new code. Put these tests behind the feature flag (FEATURE:skipTestIfInActive('FEATURE_NEXT_11111')).
    * Changed Code
        * The changed code should be hidden behind a feature flag. In most cases, this will be an if-else clause with FEATURE:isActive('FEATURE_NEXT_11111') with an additional expressive comment on what has to be done on feature release.
    * Removed Code
        * On this kind of changes there should barely remove code, except for private code. The private code which will be removed should be annotated with @feature-deprecated (flag:FEATURE_NEXT_11111) with an additional expressive comment on what has to be done on feature release.
* On Feature release
    * New Code
        * Remove the feature flag and @internal annotation.
        * Remove the feature flag from the tests.
    * Changed Code
        * Remove feature flag and keep new solution. In case you had an if-else clause, you will keep the new code and remove the old, according to the comment made before.
    * Removed Code
        * Remove feature-deprecated annotation, and the unused code according to the comment made before.

#### Only Minor Changes (with deprecating code)
Feature and Changes tend to be released in a minor release and are developed in a backward compatible manner, but deprecate old code. For example a class is replaced by a new one.
* While developing
    * New Code
        * The new code should be hidden behind a feature flag and be annotated with @internal (flag:FEATURE_NEXT_11111) for new code public api [^1].
        * Add new tests for the new code. Put these tests behind the feature flag (FEATURE:skipTestIfInActive('FEATURE_NEXT_11111')).
    * Changed Code
        * The changed code should be hidden behind a feature flag. In most cases, this will be an if-else clause with FEATURE:isActive('FEATURE_NEXT_11111') with an additional expressive comment on what have to be done on feature release.
    * Removed Code / Deprecated code
        * The obsolete code has to hidden behind the minor-flag and annotated as @feature-deprecated.
        * The call of the deprecated code has to be hidden behind an extra feature flag, especially for the major version. Also the code has to be annotated with @major-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) [^2].
        * Create a Jira ticket as a reminder, to remove the extra feature flag which is tagged for the major version. The issue key of this new ticket will be the major feature flag for this feature.
* On Feature release
    * New Code
        * Remove the feature flag and the @internal annotations.
        * Remove the feature flag from the tests.
    * Changed Code
        * Remove feature flag and keep new solution. In case you had an if-else clause, you will keep the new code and remove the old, according to the comment made before.
        * In case of additions to public api [^1] you have to make the change in a backwards compatible way (e.g. make an additional parameter optional until the next major)
    * Removed Code / Deprecated code
        * For private (non-breaking changes): Remove feature-deprecated annotation, and the unused code according to the comment made before.
        * For deprecations: Add FEATURE::triggerDeprecated('FEATURE_NEXT_22222', 'v6.4.0', 'use myNewMethod instead') to deprecated code. Change @feature-deprecated to @deprecated annotation.
        * Declare old tests as legacy. https://symfony.com/doc/current/components/phpunit_bridge.html#mark-tests-as-legacy
* On Major release
    * Removed Code
        * Remove @deprecated and @major-deprecated code. Remove extra feature flag for major version.

#### Minor Changes as part of Major Feature (No Breaks)
Parts of a major feature, which can be changed without breaking anything. New classes, private method changes with unchanged output.
* Depending on the value of the new changes, the developers should decide if it is an advantage to release the backward-compatible parts early with a minor version. In that case the previous workflow (Only Minor Changes (with deprecating code)) has to be used. Otherwise, the changes stay behind the major feature flag with the other breaking changes and follow the guid below (Major Changes as part of Major Feature (Breaks))

#### Major Changes as part of Major Feature (Breaks)
Parts of a major feature or refactoring which breaks current behaviour. Removal of classes, methods or properties, change of signatures, business logic changes...

* While developing
    * New Code
        * The new code should be hidden behind a major feature flag and be annotated with @internal (flag:FEATURE_NEXT_11111) for new code public api [^1].
        * Add new tests for the new code. Put this tests behind the major feature flag (FEATURE:skipTestIfInActive('FEATURE_NEXT_22222')).
    * Changed Code
        * The changed code should be hidden behind a major feature flag. In most cases, this will be an if-else clause with FEATURE:isActive('FEATURE_NEXT_22222') with an additional expressive comment on what have to be done on major release.
        * Declare old tests as legacy. https://symfony.com/doc/current/components/phpunit_bridge.html#mark-tests-as-legacy
    * Removed Code / Deprecated code
        * The call of the deprecated code has to be hidden behind the major feature flag. Also the code has to be annotated with @major-deprecated tag:v6.4.0 (flag:FEATURE_NEXT_22222) [^2].
        * For deprecations: Add FEATURE::triggerDeprecated('FEATURE_NEXT_22222', 'v6.4.0', 'use myNewMethod instead') to deprecated code.
        * Declare old tests as legacy. https://symfony.com/doc/current/components/phpunit_bridge.html#mark-tests-as-legacy
* On Major release
    * New Code
        * Remove the feature flag and the @internal annotation.
        * Remove the feature flag from the tests.
    * Changed Code
        * Remove the feature flag and keep the new solution. In case you had an if-else clause, you will keep the new code and remove the old, according to the comment made before.
        * Remove parts of the changed code which aren't called anymore.
    * Removed Code / Deprecated code
        * Remove major-deprecated annotation, and the unused code according to the comment made before.
        * Remove old tests
    
[^1]: Public api - Not only API! in case of the RestAPI but all code which is public available (public methods, public properties, services, templates, etc..)

[^2]: The version number of the **upcoming** major version, and the feature flag extra created for this issue for the major release.

Glossary:
[^3]: **old code**: Code which will be obsolete with the new feature or refactoring.
[^4]: **breaking code**: Code which is implemented with the change and will break current behaviour.
[^5]: **non breaking code**: Code which is implemented with the change but will not break any previous behaviour.
[^6]: **major-flag**: A feature flag, which hides breaking code, that can only be released with the next major version.
[^7]: **minor-flag**: A feature flag, which will be used while developing, to secure unfinished code changes. This flag will be removed as soon as the feature or change is completed and approved.
[^8]: **code-basis**: The whole code of shopware platform, development and production.
[^9]: **feature flags**: A feature flag is a toggle, which switches code behaviour. Basically, there are two kinds of feature flags: minor-flags and major-flags. The flag is build from an  epic- or issue key. If a major-flag is needed because of deprecations in a minor feature, a special ticket should be created for the task to remove the deprecations on major release. ("NEXT-22222 - Remove feature flag for better order view")
[^10]: **soft-break**: A soft-break is a break, which doesn't cause any errors, but leads to changes and/or inconsistencies in the business logic. E.g. it would be a soft-break if the calculation of prices gets a rounding that was not there before. This will lead to changing prices.
