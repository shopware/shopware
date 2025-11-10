# System Configuration

## Context

The `SystemConfig` module provides a capability for managing system-wide and sales-channel-specific configurations in Shopware. It allows fetching, setting, and overriding configuration values, which can be defined by the core, plugins, or apps.

## How it works

The core of the module is the `SystemConfigService`, which provides a simple API to interact with the configuration. It allows getting and setting configuration values for the entire system or for a specific sales channel.

Configurations are stored in the database in the `system_config` table. They are key-value pairs, where the key is a string (e.g., `core.newsletter.doubleOptIn`) and the value is a JSON-encoded value.

### Configuration Loading

The loading of the configuration is implemented with a decorator pattern to allow for multiple layers of caching.

1.  `SystemConfigLoader`: The base loader retrieves configuration values from the database. It handles inheritance, merging default values, and sales channel-specific configurations. It also filters out non-active plugins configs.
2.  `CachedSystemConfigLoader`: This decorator adds a layer of caching using Symfony's cache component.
3.  `MemoizedSystemConfigLoader`: This adds an in-memory, per-request cache.
4.  `ConfiguredSystemConfigLoader`: This decorator allows overriding configuration values with values from the `config/packages/*.yaml` files. This is useful for settings that should not be changed at runtime.

The `SystemConfigService` is injected with the outermost loader of this chain (for current decorators priority check src/Core/System/DependencyInjection/configuration.xml).

## Major Design Decisions

*   **Sales Channel Inheritance**: Configurations can be set globally or for a specific sales channel. When a configuration is requested for a sales channel, the system first looks for a value specific to that sales channel. If it doesn't find one, it falls back to the default (global) value. This allows for fine-grained control over the configuration of each sales channel.
*   **Read-only configurations**: Some configurations can be set via `config/packages/*.yaml` files. These will be considered read-only and cannot be changed from the administration panel or API.
*   **Decorator Pattern for Loaders**: This was chosen to make the system extensible and to separate concerns.
*   **Event System**: The module uses the events for notifying about configuration changes and allowing extensions to modify configuration on different stages of configuration life cycle.

## Internals/Usage Details

### Using the SystemConfigService

To get a configuration value, you can inject the `SystemConfigService` and use the `get` method:

```php
$value = $this->systemConfigService->get('myPlugin.config.mySetting', $salesChannelId);
```

There are also type-safe methods like `getString`, `getInt`, `getFloat`, and `getBool`.

To set a configuration value, you can use the `set` method:

```php
$this->systemConfigService->set('myPlugin.config.mySetting', 'myValue', $salesChannelId);
```

You can also set multiple values at once with `setMultiple`.

### Managing plugin configurations

Plugins can define their configuration in a `config.xml` file. This file is parsed by the `Shopware\Core\System\SystemConfig\Util\ConfigReader` and the values are saved to the database when the plugin is installed. The `SystemConfigService` provides `savePluginConfiguration` and `deletePluginConfiguration` methods to manage the lifecycle of plugin configurations.

### API Endpoints

The module provides several API endpoints to manage configurations:

*   `GET /api/_action/system-config/check`: Checks if a configuration domain (like `core.store`) exists.
*   `GET /api/_action/system-config/schema`: Gets the configuration schema for a domain (used for UI rendering).
*   `GET /api/_action/system-config`: Gets the configuration values for a domain.
*   `POST /api/_action/system-config`: Saves configuration values (for single SalesChannel or for global scope).
*   `POST /api/_action/system-config/batch`: Saves a batch of configuration values (for multiple SalesChannels).

### Console Commands

The module also provides two console commands to get and set configuration values:

*   `bin/console system:config:get`
*   `bin/console system:config:set` 