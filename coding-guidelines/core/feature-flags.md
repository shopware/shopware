## Introduction
Feature flags enable the developer to create new code which is hidden behind the flag and merge it into the trunk branch, even when the code is not finalized.
We use this functionality to merge breaks into the trunk early, without them already being switched active. To learn more about breaking changes and backward compability take a look to our [Backward Compatibility Guide](https://developer.shopware.com/docs/resources/guidelines/code/backward-compatibility.html)

### Activating the flag
To switch flags on and off you can use the ***.env*** to configure each feature flag. Using dots inside an env variable are not allowed, so we use underscore instead:
```
V6_5_0_0=1
```

## Using flags in PHP
The feature flag can be used in PHP to make specific code parts only executable when the flag is active.

### Using flags in methods
When there is no option via the container you can use additional helper functions:
```php
use Shopware\Core\Framework\Feature;
 
class ApiController
{

  public function indexAction(Request $request)
  {
    // some old stuff
    Feature::ifActiveCall('v6.5.0.0', $this, 'handleNewFeature', $request);
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
    Feature::ifActive('v6.5.0.0', function() use ($request) {
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
    if (!Feature::isActive('v6.5.0.0')) {
      //some old stuff
      return;
    }
    // awesome new stuff
  }
}
```
Putting the old behaviuor inside the if block makes it easier to remove the feature flag later on.

And you can use it simply to throw exceptions:
```php
use Shopware\Core\Framework\Feature;
 
/**
 * @deprecated tag:v6.5.0 - Class is deprecated, use ... instead
 */
class ApiController
{
  public function indexAction(Request $request)
  {
     Feature::triggerDeprecationOrThrow('v6.5.0.0', 'Class is deprecated, use ... instead');
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
     Feature::skipTestIfActive('v6.5.0.0', $this);

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
    flag: 'v6.5.0.0',
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
<sw-field type="text" v-if="feature.isActive('v6.5.0.0')"></sw-field>
```

### Using flags in config.xml

When you want to toggle config input fields in config.xml like [basicInformatation.xml](https://gitlab.shopware.com/shopware/6/product/platform/-/blob/trunk/src/Core/System/Resources/config/basicInformation.xml), you can add a `flag` element like this:

```xml
<input-field type="bool" flag="v6.5.0.0">
  <name>showTitleField</name>
  <label>Show title</label>
  <label lang="de-DE">Titel anzeigen</label>
  <flag>v6.5.0.0</flag>
</input-field>
```

## Using flags in the storefront
In the Storefront it works nearly similar to the admin.

### Using flags in storefront JavaScript
```
import Feature from 'src/helper/feature.helper';
...
data() {
   if (Feature.isActive('v6.5.0.0')) {
        console.log('v6.5.0.0 is active')
   }
 };
```

### Using flags in storefront templates
```
{% if feature('v6.5.0.0') %}
    <span>Feature is active</span>
{% endif %}
```


## Using flags in plugins
Feature flags can also be used in plugins. Among other things, by adding your own flags, but also the use of the major feature flag is an intended use case.

### Major feature flag
As mentioned before, we use the major feature flags (`v6.5.0.0`, `v6.6.0.0`) to signal breaks within the code ahead of time. This is an incredible help in the preparation of the next major release, as otherwise all breaks would have to be made within a short period of time.

This procedure can also be applied to plugins, which also use this flag and internally query it to either prepare the plugin for the next major or to support multiple Shopware major versions with one plugin version. Since each major feature flag remains after the corresponding release, they can be used as an alternative version switch to the php equivalent `version_compare`.

### Own plugin flags
<alert-box type="warning">This is internal only and we may break this behaviour at any time!</alert-box>

When you need to implement a feature flag for a plugin you can't edit the feature.yaml or provide an override for it,
so you have to register the new flag "on the fly".
```php
    private const FEATURE_FLAGS = [
        'paypal:v1.0.0.0'
    ];
...
    public function boot(): void
    {
        Feature::setRegisteredFeatures(
            array_merge(array_keys(Feature::getAll()), self::FEATURE_FLAGS),
            $this->container->getParameter('kernel.cache_dir') . '/shopware_features.php'
        );
...
```

Now your own feature flag can be handled like every core flag.
