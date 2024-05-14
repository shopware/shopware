---
title: App scripts
date: 2021-10-21
area: core
tags: [app-system, app-scripts]
---

## Context

To improve the abilities of Apps, they should be able to execute code synchronously and hook into familiar places like:

- rules
- cart
- storefront page loading
- shipping method calculation
- flow builder extensions

The app system requires that this code is in some way sandboxed, with no direct access to the database or filesystem, and the code is not saved on the server.

Additionally, such a Scripting feature generally improves the capabilities of the AppSystem, this feature is not bound to the AppSystem exclusively, it should be possible to add standalone scripts.

## Decision

We use Twig as it brings a secure PHP sandbox and allows interacting directly with objects. The scripts will be saved in the database and mapped to a specific scripting event. 
Scripting events are placed in many sections in Shopware to be able to adjust them. Apps can subscribe to the scripting events by placing their scripts into the correspondingly named folders.

### Scripting Events

The data passed to the scripts always has to be an object so that the manipulation from Twig can affect the given value. 
Given objects must be wrapped into custom objects for app scripting to provide easier access to certain functionality and limit the scripting scope. 
The twig environment will provide additional functions like `dal_search` globally to all events to fetch other data in a consequent way

#### Which objects can be injected into the hooks and which have to be wrapped

In general, it ok to inject `Struct` classes directly into the hooks, as long as those are rather "dumb" data containers (e.g. our DAL entity classes or the storefront page classes).
A notable Exception to this rule are `Struct` classes that provide business logic, besides simple getters and setters (e.g. the Cart struct).
Those `Structs` and all other `Services` that provide business logic or function that can lead to side effects (DB access, etc.) need to be wrapped into a facade.
This will allow us to closely control the interface we want to provide inside the app scripts, to firstly improve developer experience by tailoring the API to the needs of app developers and secondly to ensure that we don't introduce any security issues with the app scripts.

### Scripting execution

Each script has its twig environments to improve execution stability. In failure cases, we will throw our exception. 
The twig environment is reduced to the only set of functionality that is needed; features like block and many template features are disabled.
Script loading can happen in multiple implementations, the default implementation will use the object cache to load the scripts and if missing loading it from the database.
The compiled scripts will be cached on the filesystem in a separate folder per app and per appVersion. 
For development purposes, the scripts can be loaded from the filesystem to allow easier development. The default Twig cache will be used for faster code execution.

### Example pseudo-code of the ScriptEventRegistry

```php
class ScriptEventRegistry
{
    public const EVENT_PRODUCT_PAGE_LOADED = 'product-page-loaded';

    private $scripts = [];
    private LoggerInterface $logger;
    
    public function execute(string $hook, array $context)
    {
        $scripts = $this->scripts[$hook] ?? [];
        foreach ($scripts as $script) {
            $this->executeScript($script, $context);
        }
    }
    
    private function executeScript(array $script, array $context) 
    {
        $twig = $this->initEnv($script);

        try {
            $twig->render($script['name'], $context);
        } catch (\Throwable $e) {
            throw new ScriptExecutionFailed('Script execution failed', $e);
            $this->logger->error('Execution of script failed', ['context' => $context, 'error' => $e]));
        }
    }
    
    private function initEnv(array $script) 
    {
        $cache = new ConfigurableFilesystemCache($this->cachePath . '/twig/scripts');
        $cache->setConfigHash($script['appName'] . $script['appVersion']);
        
        $twig = new Environment(
            new ScriptLoader([$script['name'] => $script['source']]),
            [
                'cache' => $cache,
            ]
        );
        
        // Setup some custom twig functions
        
        return $twig;
    }
}
```

### Example pseudo-code

#### Getting discount for high value order

```twig
{% if cart.price.totalPrice > 500 %}
    {# get discount for high value orders #}
    {% do cart.discount('percentage', 10, 'my_discount_snippet', cart.lineItems) %}
{% endif %}
```

#### Block cart

```twig
{% if cart.price.totalPrice < 500 %}
    {# allow only carts with high values #}
    {% do cart.block('you have to pay at least 500€ for this cart') %}
{% endif %}
```

### Data Loading

To allow apps to fetch additional data for the storefront, we will introduce PageLoaded-Hooks.
Those hooks will orient themself on the Page and PageLoadedEvents already present in the storefront. So for each PageType and PageLoadedEvent we will create a separate Hook class.
We will create separate HookClasses and not just one generic class, so we are able to type hint all the dynamic data that is available for that hook. That will improve the developer experience as it allows for autocompletion in the scripts and allows us to generate documentation for the hooks.
The hooks will be instantiated and passed to the HookExecutor from the Controllers where the pages are loaded, so we are able to pass additional data if it is needed or makes sense.
Additionally, we explicitly decided to not provide CriteriaEvent-Hooks, as that idea is contrary to the direction we may want to go with a separate and specialized data view for the storefront.

### Documentation

To ensure app developers can use the full potential of the app scripts, we need to ensure that we document the features of app scripts extensively and make sure that the documentation is always up-to-date.
For this reason we decided to generate as much of the documentation as possible, so it never gets outdated, and it's easier to generate full reference (e.g. all hook points that exist with the associated data and available services).

## Consequences

- Added script events with the passed arguments need to be supported for a long time
- We will create a new domain-specific way to interact with shopware core domain logic. This means we have to think of and develop a higher-level description of our core domain logic and represent it through new
functions that perform domain-specific tasks. For example, the block cart function in the example above. Those domain objects represent the API of the AppScripts, therefore, breaking changes need to be considered carefully and should definitely follow our general breaking change policy. 
Additionally, the domain-specific layer may allow us to not break the public interface, when the implementation in the underlying services may break, so we can try to ensure even longer compatibility in the domain layer.
However, to make evolvability possible at all, we need to inject the shopware version into the context of the app scripts, so that in the app scripts the version can be detected and new features used accordingly.
