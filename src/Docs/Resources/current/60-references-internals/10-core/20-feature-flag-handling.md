[titleEn]: <>(Feature flag handling)
[hash]: <>(article:core_feature_flag_handling)

# Using Feature Flags
Feature flags are the preferred way for developing new features. It enables the developer to create new code which is hidden behind the flag and merge it into the master branch, even when the feature is not finalised. This makes it possible to do cross-functional tests with other features and code changes already made in the master branch.

**Attention!**

**This is a behaviour we may break at any time! Don't rely on feature flags for your functionality it's only for the development process**
___
## Working on the feature
The first thing to do is to create the feature flag. After you created the feature flag you can start working on the feature. Make sure that all feature related code is behind the feature flag and it is not executed, when the flag is not active.

When you have to introduce new deprecations, which are hidden by the feature flag, make sure to use the correct annotation.

```
@feature-deprecated (flag:FEATURE_NEXT_12345)
```

## Releasing the feature
After all changes are merged and all requirements are met, the feature flag can be removed in a separate merge request. Also the annotations for deprecations, introduced by the feature, have to be changed to the correct type.

After the feature is released in the master branch, you are not finished yet. As the final operation you create an additional merge request for the major branch, where you refactor the deprecated code parts introduced by the feature.


## Creating the flag
When you start developing a new feature, the first thing to do is to register a new feature flag.
of the corresponding epic or story as its name, by adding it to *Core/Framework/Resources/config/packages/shopware.yaml* under the key *shopware.feature.flags*

```yaml
...
shopware:
   ...
    feature:
        flags:
            ...
            # new form
            - name: FEATURE_NEXT_1130
              major: false
              default: false
              description: "example feature"
            # old form without meta data
            - FEATURE_NEXT_1129
```

The following attributes can be configured for each feature:

|attribute|type|default value|description|
|---|---|---|---|
|name|string|(is required)|The name of the feature flag|
|major|bool|`false`|Controls if a feature is intended for a major release, which usually means it breaks some APIs|
|default|bool|`false`|The default value of the feature flag, if it is not defined in the environment (`!array_key_exists('FEATURE_XXX', $_SERVER)`|
|description|string|`''`|A description of the feature

## Creating flags for Shopware plugins

**This is a behaviour we may break at any time! Don't rely on feature flags for your functionality it's only for the development process**
___

When you need to implement a feature flag for a plugin you can't edit the shopware.yaml or provide an override for it, so you have to register the new flag "on the fly".
```php
public function boot(): void
{
    Feature::registerFeature(
        'FEATURE_NEXT_555', 
        ['major' => false, 'default' => false, 'description' => 'My awesome feature']
    );
}
```

Now your own feature flag can be handled like every core flag.

## Activating the flag
To switch flags on and off you can use the ***.psh.yaml.override*** to configure each feature flag.
```
const:
  FEATURES: |
    FEATURE_NEXT_1128=1
```
This will be automatically written in the ***.env*** file.

## Using flags in PHP
The feature flag can be used in PHP to make specific code parts only executable when the flag is active.

### Using flags for services
You can use the flag in the DI-container to toggle specific services.
```xml
<service ...>
   <tag name="shopware.feature" flag="FEATURE_NEXT_1128"/>
</service>
```

Other implementations are done by using some helper functions.

### Using flags in methods
When there is no option via the container you can use additional helper functions:
```php
use Shopware\Core\Framework\Feature;
 
class ApiController
{

  public function indexAction(Request $request)
  {
    // some old stuff
    Feature::ifActiveCall('FEATURE_NEXT_1128', $this, 'handleNewFeature', $request);
    // some old stuff
  }

  private function handleNewFeature(Request $request)
  {
    // awesome new stuff
  }
}
```

You can also do it in a callback:
```php
use Shopware\Core\Framework\Feature;
 
class ApiController
{
  public function indexAction(Request $request)
  {
    // some old stuff
    Feature::ifActive('FEATURE_NEXT_1128', function() use ($request) {
      // awesome stuff
    });
    // some old stuff
  }
}
```

And you can use it for conditions:
```php
use Shopware\Core\Framework\Feature;
 
class ApiController
{
  public function indexAction(Request $request)
  {
    // some old stuff
    if (Feature::isActive('FEATURE_NEXT_1128')) {
      //awesome new stuff
    }
    // some old stuff
  }
}
```

### Using flags in tests
You can flag a test by using the corresponding helper function. This can also be used in the `setUp()` method.
```php
use Shopware\Core\Framework\Feature;
 
class ProductTest
{
  public function testNewFeature() 
  {
     Feature::skipTestIfActive('FEATURE_NEXT_1128', $this);

     // test code
  }
}
```

## Using flags in the administration
Also in the JavaScript code of the administration the flags can be used in various ways.

### Using flags for modules
You can also hide complete admin modules behind a flag:
```javascript
 
Module.register('sw-awesome', {
    flag: FEATURE_NEXT_1128,
    ...
});
```

### Using flags in JavaScript
To use a flag in a VueJS component you can inject the feature service and use it.

```
inject: ['feature'],
...
featureIsActive(flag) {
    return this.feature.isActive(flag);
},
```

### Using flags in templates
When you want to toggle different parts of the template you can use the flag in a VueJs condition if you injected the service in the module:
```html
<sw-field type="text" v-if="feature.isActive('FEATURE_NEXT_1128')"></sw-field>
```

## Using flags in the storefront
In the Storefront it works nearly similar to the admin.

### Using flags in storefront JavaScript
```
import Feature from 'src/helper/feature.helper';
...
data() {
   if (Feature.isActive('FEATURE_NEXT_1128')) {
        console.log('FEATURE_NEXT_1128 ist Aktiv')
   }
 };
```

### Using flags in storefront templates	
```
{% if feature('FEATURE_NEXT_1128') %}
    <span>Feature ist Aktiv</span>
{% endif %}
```

# What has to be behind a flag?
*  Code, which is changed or introduced by the new feature, should not be executed.
*  Everything that can be reached from external calls needs to be hidden behind the flag.  
   For example: routes, API definitions, schema, etc.
*  Everything that is not possible to hide needs to be annotated. For example: new constants, new classes

**New Entity Definitions**  
New Entity Definitions have to be hidden behind the flag in the container. See [Using flags for services](#using-flags-for-services)

**New Services and other classes (subscriber, event, resolver)**  
New Services have to be hidden behind the flag in the container. See [Using flags for services](#using-flags-for-services)

**Changes in current classes**  
Changes inside current classes should be conditioned with the flag. See [Using flags in methods](#using-flags-in-methods)

**Additions to current classes**  
Additions to current classes, like constants or public functions, can often not be flagged. In this case you have to annotate the not available part with an *@internal (flag:FEATURE_NEXT_1128)* comment.
```php
//@internal (flag:FEATURE_NEXT_1128)
const NEW_FEATURE_CONST = true;
```

**New Routes**  
New Routes have to return the *NotFoundHttpException* if the flag is not active.
