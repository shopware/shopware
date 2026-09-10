## Introduction
Feature flags enable the developer to create new code which is hidden behind the flag and merge it into the trunk branch, even when the code is not finalized.
We use this functionality to merge breaks into the trunk early, without them already being switched active. To learn more about breaking changes and backward compability take a look to our [Backward Compatibility Guide](https://developer.shopware.com/docs/resources/guidelines/code/backward-compatibility.html)

Related ADR: [Feature flags for major versions](../../adr/2022-01-20-feature-flags-for-major-versions.md).

### Activating the flag
To switch flags on and off you can use the ***.env*** to configure each feature flag. Using dots inside an env variable are not allowed, so we use underscore instead:
```
V6_5_0_0=1
```

### Activating whole groups of flags
`FEATURE_ALL` switches a group on at once, which is how the test lanes run:

| Value | Active flags |
|---|---|
| `1`, `minor`, any truthy value except `false` | every non-major flag |
| `major` | every major flag |
| `v6.8.0.0` | the major flags arriving in v6.8.0.0 or earlier |

A flag configured in the environment always wins over `FEATURE_ALL`.

A major flag named after its major (`v6.8.0.0`) carries the major it arrives in. One that is not
(`JSON_LD_DATA`, `BREADCRUMB_REWORK`) belongs to every major, so it is active in every major lane;
declare `majorVersion` when the flag may only be active from a later major on:

```yaml
      - name: JSON_LD_DATA
        default: false
        major: true
        majorVersion: v6.9.0.0
        toggleable: true
```

## While two majors are in flight

Trunk then carries the flags of both majors, and "all majors on" no longer describes any release
state: 6.9 changes decide the outcome of a 6.8 assertion. CI therefore runs one lane per unreleased
major (`FEATURE_ALL=v6.8.0.0`, `FEATURE_ALL=v6.9.0.0`) in `integration-major.yml` and in the major
arm of `acceptance.yml`. The lanes come from `feature.yaml` itself — a `major: true` flag named after
its version and still `default: false` is a lane, see `.github/bin/lib/feature-flags.php` — so
registering the next major flag adds its lane, with nothing to maintain in the workflows.

The unit suite is the exception: its bootstrap activates every registered flag regardless of
`FEATURE_ALL`, so a unit test always sees the newest major and has to pin itself explicitly — see
[Using flags in tests](#using-flags-in-tests).

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

## Planning public API changes

Plan an API break for the next major with the matching attribute from
`Shopware\Core\Framework\Deprecation\BCChange`, for example
`#[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'id', newType: 'string')]`.
These attributes describe a future contract change; they are not deprecations and the current API
must remain usable until the announced version. Do not use `@deprecated reason:*` for this purpose:
those annotations are treated as actionable deprecations by third-party static analysis even when
there is no replacement today.

Choose the attribute according to the affected audience. A
`CallSiteCompatibilityChange` can break code that invokes a method, including a `parent::` call in
a subclass. An `ExtenderCompatibilityChange` can break a subclass's override declaration or its
inheritance relationship. Some attributes affect both. Use a real `@deprecated` annotation only
when functionality is removed or has a replacement that callers must use now.

For a planned change whose legacy use can be identified while the current API is executed
(`BecomesAbstract`, `NewRequiredParameter`, `ParameterRemoval`, or `ParameterTypeNarrowing`),
keep the old behavior and call `Feature::triggerDeprecationOrThrow()` only for the incompatible
legacy use. This provides a runtime migration signal before the declared signature change.
Framework-invoked methods are the exception because the framework would trigger the warning for
legitimate calls.

Use a `vX.Y.Z` version, parameter names without `$`, `::class` for class references, and the
actual default value for `NewOptionalParameter`. PHPStan validates these conventions and rejects
attributes that do not describe a real future change.

### Using flags in tests
In unit tests, current major feature flags are active by default. Test legacy/off behavior by disabling the relevant flag with the `#[DisabledFeatures]` attribute instead of calling `Feature::fake()` just to activate the current major flag.

`#[DisabledFeatures]` only works in the unit suite: the feature-flag test extension processes `Shopware\Tests\Unit\` (plus namespaces registered via `FeatureFlagExtension::addTestNamespace()`). In integration tests the flag state comes from the job configuration (`FEATURE_ALL`), the attribute has no effect, and the test runner rejects it — a test carrying it fails the run. When an integration test must not run under a specific flag state, skip it at runtime with `Feature::skipTestIfActive()` / `Feature::skipTestIfInActive()`.

```php
use Shopware\Core\Test\Annotation\DisabledFeatures;

class ProductTest
{
  #[DisabledFeatures(['v6.5.0.0'])]
  public function testLegacyFeature()
  {
     // test code
  }
}
```

While two majors are in flight, pin a test to the older one by disabling the newer major: `#[DisabledFeatures(['v6.9.0.0'])]` asserts the 6.8 state, `#[DisabledFeatures(['v6.8.0.0', 'v6.9.0.0'])]` the state before either major.

In integration tests, the suite may run multiple times with different feature-flag states. Keep using `Feature::skipTestIfActive()` or `Feature::skipTestIfInActive()` when a scenario only makes sense for one state of a flag. This can also be used in the `setUp()` method. That is also how an integration test pins itself to a single major — `Feature::skipTestIfActive('v6.9.0.0', $this)` keeps it out of the 6.9 lane.

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
