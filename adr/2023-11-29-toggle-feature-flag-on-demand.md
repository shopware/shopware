---
title: Make feature flags toggleable on demand
date: 2023-11-29
area: core, administration, storefront
tags: [core, feature, experimental]
---

## Context

Feature flags are a great way to enable/disable features in the application. However currently, they are not toggleable on demand. This means that if you want to enable a feature flag, you need to change the environment variables and restart the application. This is not ideal for a production environment.

## Decision

### Store feature flags in the database

The available features are currently stored in the `feature.yaml` static file and toggleable via environment variables. We want to provide a way, that we can toggle this feature flags also via database and provide an UI for the shop merchant.

#### Example feature flag configuration in `app_config`

| key           | value                                                                                                                                                                                                          | 
|---------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| feature.flags | <pre>{ <br/>    EXAMPLE_FEATURE_1:{ name: EXAMPLE_FEATURE_1, default: true, active: true<br/>    EXAMPLE_FEATURE_2:{ name: EXAMPLE_FEATURE_2, default: true, active: false<br/>}</pre> |

All activated feature flags should be registered on `Framework::boot` via `FeatureFlagRegistry::register`:

```php
class Framework extends Bundle
    public function boot(): void
    {
        ...
        $featureFlagRegistry = $this->container->get(FeatureFlagRegistry::class);
        $featureFlagRegistry->register();
    }
```

`FeatureFlagRegistry::registry`: in this public method, we merge the static feature flags from `feature.yaml` with the stored feature flags from the database, we then activate the feature flags which are marked as active.

```php
class FeatureFlagRegistry
{
    public function registry(): void
    {
        $static = $this->featureFlags;
        $stored = $this->keyValueStorage->get(self::STORAGE_KEY, []);

        if (!empty($stored) && \is_string($stored)) {
            $stored = \json_decode($stored, true, 512, \JSON_THROW_ON_ERROR);
        }
        
        // Major feature flags cannot be toggled with stored flags
        $stored = array_filter($stored, static function (array $flag) {
            return !\array_key_exists('major', $flag) || !$flag['major'];
        });

        $flags = array_merge($static, $stored);
        
        Feature::registerFeatures($flags);
    }
}
```

### Toggle feature flags on demand

We introduce new admin APIs so we can either activate/deactivate the feature flags.
**Note:** We should only allow toggling feature flags which is not major.

#### Admin API

```php
class FeatureFlagController extends AbstractController
{
    #[Route("/api/_action/feature-flag/enable/{feature}", name="api.action.feature-flag.toggle", methods={"POST"})]
    public function enable(string $feature, Request $request): JsonResponse
    {        
        $this->featureFlagRegistry->enable($feature);
        
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    
    #[Route("/api/_action/feature-flag/disable/{feature}", name="api.action.feature-flag.toggle", methods={"POST"})]
    public function disable(string $feature, Request $request): JsonResponse
    {        
        $this->featureFlagRegistry->disable($feature);
        
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route("/api/_action/feature-flag", name="api.action.feature-flag.load", methods={"GET"})]
    public function load(Request $request): JsonResponse
    {
        $featureFlags = Feature::getRegisteredFeatures();
        
        return new JsonResponse($featureFlags);
    }
}
```

`FeatureFlagRegistry::enable` & `disable` methods: in these public methods, we enable feature flags and store the new state in the database. We also dispatch an event `BeforeFeatureFlagToggleEvent` before toggling the feature flag and `FeatureFlagToggledEvent` after toggling the feature flag. This is helpful for plugins to listen to these events and do some actions before/after toggling the feature flag

```php
class FeatureFlagRegistry
{
    private function enable(string $feature, bool $active): void
    {
        $registeredFlags = Feature::getRegisteredFeatures();
        
        if (!array_key_exists($feature, $registeredFlags)) {
            return;
        }
        
        if ($registeredFlags[$feature]['major'] === 'true') {
            // cannot toggle major feature flags
            return;
        }
        
        $registeredFlags[$feature] = [
            'active' => $active, // mark the flag as activated or deactivated
            'static' => array_key_exists($feature, $this->staticFlags), // check if the flag is static
            ...$registeredFlags[$feature],
        ];
                
        $this->dispatcher->dispatch(new BeforeFeatureFlagToggleEvent($feature, $active));

        $this->keyValueStorage->set(self::STORAGE_KEY, $registeredFlags);
        Feature::toggle($feature, $active);

        $this->dispatcher->dispatch(new FeatureFlagToggledEvent($feature, $active));
    }
}
```

#### CLI

We can also toggle the feature flags via CLI

```script
// to enable the feature FEATURE_EXAMPLE
bin/console feature:enable FEATURE_EXAMPLE 

// to disable the feature FEATURE_EXAMPLE
bin/console feature:disable FEATURE_EXAMPLE

// to list all registered feature flags
bin/console feature:list
```

## Consequences

### Ecosystem

- Before this, Feature flag system was mostly considered as an internal dev-only tool, it's used to hide major breaks or performance boost. 
- Now it elevates to be a place where we can introduce new features and hide them behind feature flags. This will allow us to delivery new features even at experimental/beta phase and try them in production on demand without affecting the shop merchants
- But this should not be abused, we could only use the toggle for experimental/beta features and not for major features

### Commercial plans

- For commercial licenses, each license's feature should be treated as a feature flag. This way, we can enable/disable features for each license if it's available in the license

### Shop merchants

- For shop merchants, they can use the new toggle feature flags API to enable/disable features on demand, this will override the environment variables if the feature flag is available in the database.  We can also add a new admin module or an app to allow shop merchants to toggle feature flags on demand or list all available feature flags via new admin APIs

### Developers

- For internal devs, they can utilize the tool to quickly delivery new experimental/beta features. However, it's important that this should not be a tool to reach deadlines or release "crap". We should still follow standards and guidelines. 
- External plugins can also add their own feature flags by adding them to the `feature.flags` key in the key value storage (e.g. `app_config` table if using the default key value storage)
- Feature flags can be toggled via CLI using `bin/console feature:enable <feature>` or `bin/console feature:disable <feature>` this is helpful for testing purposes and for CI/CD pipelines
- We can also add a new CLI command to list all available feature flags and their status using `bin/console feature:list`
- When a feature flag is toggled at run time, we dispatch an event `BeforeFeatureFlagToggleEvent` before toggling the feature flag and `FeatureFlagToggledEvent` after toggling the feature flag. This is helpful for plugins to listen to these events and do some actions before/after toggling the feature flag
