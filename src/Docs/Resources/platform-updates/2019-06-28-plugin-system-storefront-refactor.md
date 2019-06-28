[titleEn]: <>(Storefront Plugin system refactoring)

We reorganized the directory structure of the JavaScript in the Storefront as well as added a new convenient method
to override plugins. **This is a breaking change!** 

The import path for the plugin manager, the plugin class as well as the plugin config manager has changed:

### Breaking change

#### Before:
```
import Plugin from 'src/script/helper/plugin/plugin.class.js';
import PluginManager from 'src/script/helper/plugin/plugin.manager.js'
import PluginConfigManager from 'src/script/helper/plugin/plugin.config.manager.js'
```

#### After:
```
import Plugin from 'src/script/plugin-system/plugin.class.js';
import PluginManager from 'src/script/plugin-system/plugin.manager.js'
import PluginConfigManager from 'src/script/plugin-system/plugin.config.manager.js'
```

### Overriding plugins
It was possible to override Storefront plugins using the `PluginManager.extend()` method using the following syntax:

```
PluginManager.extend('OffCanvasCart', 'OffCanvasCart', MyOffCanvasCartPlugin, '[data-offcanvas-cart]');
``` 

The new convenient method can be used as the following:

```
PluginManager.override('OffCanvasCart', MyOffCanvasCartPlugin, '[data-offcanvas-cart]');
``` 