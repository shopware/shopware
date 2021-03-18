[titleEn]: <>(Add SCSS variables in a plugin)
[metaDescriptionEn]: <>(This HowTo will show you how you can add custom SCSS variables in a plugin.)
[hash]: <>(article:how_to_plugin_scss_variables)

## Overview

With [themes](./../30-theme-guide/__categoryInfo.md) it is possible to add custom SCSS variables through the theme.json config fields. Since there is no theme.json in regular plugins you can use a subscriber class to add custom SCSS variables.

Before you start adding your subscriber you should provide a fallback value for your custom SCSS variable in your plugin `base.scss`:
```scss
// ScssPlugin/src/Resources/app/storefront/src/scss/base.scss

// The value will be overwritten by the subscriber when the plugin is installed and activated
$sass-plugin-header-bg-color: #ffcc00 !default;

.header-main {
    background-color: $sass-plugin-header-bg-color;
}
```

## Theme variables subscriber

You can add a new subscriber according to the [plugin subscriber documentation](./040-register-subscriber.md). In this example we name the subscriber `ThemeVariableSubscriber.php`". The subscriber listens to the `ThemeCompilerEnrichScssVariablesEvent`.

```php
// ScssPlugin/src/Subscriber/ThemeVariablesSubscriber.php
namespace ScssPlugin\Subscriber;

use Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ThemeVariablesSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ThemeCompilerEnrichScssVariablesEvent::class => 'onAddVariables'
        ];
    }

    public function onAddVariables(ThemeCompilerEnrichScssVariablesEvent $event)
    {
        // Will render: $sass-plugin-name-font: "Helvetica Neue", Arial, sans-serif;
        $event->addVariable('sass-plugin-name-font', '"Helvetica Neue", Arial, sans-serif');

        // Will render: $sass-plugin-name-color: #ffcc59;
        $event->addVariable('sass-plugin-name-color', '#ffcc59');

        // Will render: $sass-plugin-name-special: 'My special string';
        $event->addVariable('sass-plugin-name-special', 'My special string', true);
    }
}
```

* The `ThemeCompilerEnrichScssVariablesEvent` provides the `addVariable()` method which takes the following parameters:
  
  1. `$name:` (string)<br>
     The name of the SCSS variable. The passed string will be exactly in your SCSS so please be careful with special characters. We recommend using kebab-case here. The variable prefix `$` will be added automatically. We also recommend prefixing your variable name with your plugin's or company's name to prevent naming conflicts.
  2. `$value:` (string)<br>
     The value which should be assigned to the SCSS variable.
  3. `$sanitize` (bool - optional)<br>
     Optional parameter to remove special characters from the variables value. The parameter will also add quotes around the variables value. In most cases quotes are not needed e.g. for color hex values. But there may be situations where you want to pass individual strings to your SCSS variable.
* Please note that plugins are not sales channel specific. Your SCSS variables are directly added in the SCSS compilation process and will be globally available throughout all themes and storefront sales channels. If you want to change a variables value for each sales channel you should use plugin config fields and follow the next example.

## Plugin config values as SCSS variables

Inside your `ThemeVariablesSubscriber.php` you can also read values from the plugin configuration and assign those to a SCSS variable. This makes it also possible to have different values for each sales channel. Depending on the selected sales channel inside the plugin configuration in the administration.

First of all lets add a new plugin configuration field according to the [plugin configuration documentation](./../60-references-internals/40-plugins/070-plugin-config.md):

```xml
<!-- ScssPlugin/src/Resources/config/config.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/System/SystemConfig/Schema/config.xsd">

    <card>
        <title>Example configuration</title>
        <input-field>
            <name>exampleColor</name>
            <label>Example color field</label>
        </input-field>
    </card>
</config>
```

To be able to read this config you have to add the `SystemConfigService` to your subscriber:

```php
// ScssPlugin/src/Subscriber/ThemeVariablesSubscriber.php
namespace ScssPlugin\Subscriber;

// ...
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ThemeVariablesSubscriber implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    protected $systemConfig;

    // add the `SystemConfigService` to your constructor
    public function __construct(SystemConfigService $systemConfig)
    {
        $this->systemConfig = $systemConfig;
    }

    // ...

    public function onAddVariables(ThemeCompilerEnrichScssVariablesEvent $event)
    {
        /** @var string $configExampleField */
        $configExampleField = $this->systemConfig->get('ScssPlugin.config.exampleColor', $event->getSalesChannelId());

        // pass the value from `exampleColor` to `addVariable`
        $event->addVariable('sass-plugin-example-color', $configExampleField);
    }
}
```
* The `SystemConfigService` provides a `get()` method where you can access the configuration structure in the first parameter with a dot notation syntax like `MyPluginName.config.fieldName`. The second parameter is the sales channel id. With this id the config fields can be accessed for each sales channel.
* You can get the sales channel id through the getter `getSalesChannelId()` of the `ThemeCompilerEnrichScssVariablesEvent`.
* Now your sass variables can have different values in each sales channel.

### All config fields as SCSS variables

Adding config fields via `$event->addVariable()` individually for every field may be a bit cumbersome in some cases. You could also loop over all config fields and call `addVariable()` for each config field. But this depends on your use case.

```php
// ScssPlugin/src/Subscriber/ThemeVariablesSubscriber.php
namespace ScssPlugin\Subscriber;

// ...
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class ThemeVariablesSubscriber implements EventSubscriberInterface
{
    // ...

    public function onAddVariables(ThemeCompilerEnrichScssVariablesEvent $event)
    {
        $configFields = $this->systemConfig->get('ScssPlugin.config', $event->getSalesChannelId());

        foreach($configFields as $key => $value) {
            // Convert `customVariableName` to `custom-variable-name`
            $variableName = str_replace('_', '-', (new CamelCaseToSnakeCaseNameConverter())->normalize($key));

            $event->addVariable($variableName, $value);
        }
    }
}
```
To avoid camelCase variable names when reading from the `config.xml` we recommend to use the `CamelCaseToSnakeCaseNameConverter` to format the variable before adding it.
