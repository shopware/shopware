---
title: Feature flags for major versions
date: 2022-01-20
area: core
tags: [core, feature-flag, workflow, major-version]
---

## Context
Feature flags enable the developer to create new code which is hidden behind the flag and merge it into the trunk branch, even when the code is not finalized.
We use this functionality to merge breaks into the trunk early, without them already being switched active.

## Decision
We will use feature flags for major versions to hide new code that will be introduced in the next major version.
We have only one feature flag in our core sources: `v6.5.0.0`. This feature flag is used for the breaks mentioned above.

## Consequences
We will use the static functions of the Feature class to check if a feature is active or not. And only hide code for the next major version behind the feature flag.

### Activating the flag
To switch flags on and off, you can use the ***.env*** to configure each feature flag. Using dots inside an env variable is not allowed, so we use underscore instead:
```bash
V6_5_0_0=1
```

### Using flags in PHP:
The feature flag can be used in PHP to make specific code parts only executable when the flag is active.

### Using flags in methods
When there is no option via the container, you can use additional helper functions:
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
    if (Feature::isActive('v6.5.0.0')) {
      //awesome new stuff
    }
    // some old stuff
  }
}
```

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

### Using flags in the administration:
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

When you want to toggle config input fields in config.xml like [basicInformation.xml](https://gitlab.shopware.com/shopware/6/product/platform/-/blob/trunk/src/Core/System/Resources/config/basicInformation.xml), you can add a `flag` element like this:

```xml
<input-field type="bool" flag="v6.5.0.0">
  <name>showTitleField</name>
  <label>Show title</label>
  <label lang="de-DE">Titel anzeigen</label>
  <flag>v6.5.0.0</flag>
</input-field>
```

### Using flags in the storefront:
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


### Using flags in plugins:
Feature flags can also be used in plugins.

### Major feature flag
As mentioned before, we use the major feature flags (`v6.5.0.0`, `v6.6.0.0`) to signal breaks within the code ahead of time. This is an incredible help in the preparation of the next major release, as otherwise all breaks would have to be made within a short period of time.

This procedure can also be applied to plugins, which also use this flag and internally query it to either prepare the plugin for the next major or to support multiple Shopware major versions with one plugin version. Since each major feature flag remains after the corresponding release, they can be used as an alternative version switch to the php equivalent `version_compare`.
