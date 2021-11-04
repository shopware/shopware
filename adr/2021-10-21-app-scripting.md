# 2021-10-21 - (App-) Scripting

## Context

To improve the abilities of Apps, they should be able to execute code synchronously and hook into familiar places like:

- rules
- cart
- storefront page loading
- shipping method calculation
- flow builder extensions

The app system requires that this code is in some way sandboxed, with no direct access to the database or filesystem and the code is not saved on the server.

Additionally, such Scripting feature generally improves the capabilities of the AppSystem, this feature is not bound to the AppSystem exclusively, it should be possible to add standalone scripts.

## Decision

We use Twig as it brings a secure PHP sandbox and allows interacting directly with objects. The scripts will be saved in the database and mapped to a specific scripting event. 
Scripting events are placed in many sections in Shopware to be able to adjust them. Apps can subscribe to the scripting events by placing their scripts into the correspondingly named folders.

### Scripting Events

The data passed to the scripts always has to be an object so that the manipulation from Twig can affect the given value. 
Given objects must be wrapped into custom objects for app scripting to provide easier access to certain functionality and limit the scripting scope. 
The twig environment will provide additional functions like `dal_search` globally to all events to fetch other data in a consequent way

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

## Consequences

- Added script events with the passed arguments need to be supported for a long time
- We will create a new domain-specific way to interact with shopware core domain logic. This means we have to think of and develop a higher-level description of our core domain logic and represent it through new
functions that perform domain-specific tasks. For example, the block cart function in the example above. Those domain objects represent the API of the AppScripts, therefore breaking changes need to be considered carefully and should definitely follow our general breaking change policy. 
Additionally, the domain specific layer may allow us to not break the public interface, when the implementation in the underlying services may break, so we can try to ensure even longer compatibility in the domain layer.
However, to make evolvability possible at all we need to inject the shopware version into the context of the app scripts, so that in the app scripts the version can be detected and new features used accordingly.
