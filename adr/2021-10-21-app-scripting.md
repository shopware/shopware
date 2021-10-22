# 2021-10-21 - App Scripting

## Context

To improve the abilities of Apps, they should be able to execute code synchronously and hook into familiar places like:

- rules
- cart
- storefront page loading
- shipping method calculation
- flow builder extensions

The app system requires that this code is in some way sandboxed, with no direct access to the database or filesystem and the code is not saved on the server.

## Decision

We use Twig as it brings a secure PHP sandbox and allows interacting directly with objects. The scripts will be saved in the database and mapped to a specific scripting event. 
Scripting events are placed in many sections in Shopware to be able to adjust them. Apps can subscribe to the scripting events in the `manifest.xml`

### Scripting Events

The data passed to the scripts always has to object so that the manipulation from Twig can affect the given value. 
Given objects must be wrapped into custom objects for app scripting to provide easier access to certain functionality and limit the scripting scope. 
The twig environment will provide additional functions like `dal_search` globally to all events to fetch other data in a consequent way

### Scripting execution

Each script has its twig environments to improve execution stability. In failure cases, we will throw our exception. 
The twig environment is reduced to the only set of functionality that is needed; features like block and many template features are disabled.
Script loading can happen in multiple implementations, the default implementation will use the object cache to load the scripts and if missing loading it from the database.
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
    
    private function executeScript(string $script, array $context) 
    {
        $twig = $this->initEnv($script);

        try {
            $twig->render('script.twig', $context);
        } catch (\Throwable $e) {
            throw new ScriptExecutionFailed('Script execution failed', $e);
            $this->logger->error('Execution of script failed', ['context' => $context, 'error' => $e]));
        }
    }
    
    private function initEnv(string $script) 
    {
        $twig = new Environment(new ArrayLoader(['script.twig' => $script]));
        
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
functions that perform domain-specific tasks. For example, the block cart function in the example above.