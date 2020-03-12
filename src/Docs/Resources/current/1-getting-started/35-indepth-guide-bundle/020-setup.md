[titleEn]: <>(Step 2: The plugin setup)
[hash]: <>(article:bundle_setup)

## Basic structure

Let's start with creating the plugin.
Head over to the directory `<shopware root>/custom/plugins`.
The plugin's directory must be named after the plugin, so in this scenario `SwagBundleExample` is used throughout the full tutorial.

Each plugin is defined by a `composer.json` file, which contains a plugin's name, version, requirements and many more meta-information.
Those of you familiar with [composer](https://getcomposer.org/) might have figured out what's going on here already:
Each plugin you write can be used by composer just like any other composer package - thus every property mentioned [here](https://getcomposer.org/doc/04-schema.md) can be used
in your plugin's `composer.json` as well.

Create this file inside your new directory `SwagBundleExample` and head over to the next step.

## Filling the composer.json

So what do you need in your plugin's meta information?
Each composer package comes with a technical [name](https://getcomposer.org/doc/04-schema.md#name) as its unique identifier if you were to publish your plugin using composer.
*Note: Don't worry - just because your plugin is basically a composer package, it won't be published because of that. That's still up to you,
you'll easily be able to do so though.*

```json
{
    "name": "swag/bundle-example"
}
```

The naming pattern is the very same like the one recommended by composer:
> It consists of vendor name and project name, separated by `/`

The vendor name can also be your vendor prefix, Shopware for example uses `swag` here.
The `project name` should be separated by a `-`, often referred to as `kebab-case`.

So, what else would you need in general?
A description, a version, the used license model and probably the author.
After having a look at the [composer schema](https://getcomposer.org/doc/04-schema.md) once more, your `composer.json` could look like this:

```json
{
    "name": "swag/bundle-example",
    "description": "Bundle example",
    "version": "v1.0.0",
    "license": "MIT",
    "authors": [
        {
            "name": "shopware AG",
            "role": "Manufacturer"
        }
    ]
}
```

<dl>
    <dt>description</dt>
    <dd>
        The description should describe your composer package, or plugin in this case, in a few words.
        Make sure to write your description in english, as this would also be a public description if you were to release
        your plugin to the public.
    </dd>
    
    <dt>version</dt>
    <dd>
        A version in Shopware 6 mostly follows the specification for semantic versioning.
        The 'v' at the beginning is just a convention, since most tags in a version control system are named like that.
        It's okay though if you dismiss the 'v' prefix, your plugin will still work perfectly.
    </dd>
    
    <dt>license</dt>
    <dd>
        The license is fully up to you, just make sure you're **not** using the MIT license if you want to release your plugin
        in the Shopware Community Store (https://store.shopware.com/), since that would allow your customers to use your plugin
        and release it again with their own name and actually make some money with it.
    </dd>
    
    <dt>author</dt>
    <dd>
        Last but not least there's the author - for simplicities' sake we've only added the name here.
        You can also add an e-mail, a website and a role to this.    
    </dd>
</dl>

All of those values being used in the example are mostly used by composer.
Yet, there are plenty more values, that are required by Shopware 6, so let's have a look at them as well.

### type

First of all you can define a [type](https://getcomposer.org/doc/04-schema.md#type), which has to be `shopware-platform-plugin` here.
```json
{
    ...
    "type": "shopware-platform-plugin" 
}
```
Your plugin won't be considered to be a valid plugin if you do not set this value.

### autoload

The next value would be the [autoload](https://getcomposer.org/doc/04-schema.md#autoload) property, which works exactly like described
on the documentation linked above.
In short: You're defining your plugin's location + namespace in there.
This allows you to structure your plugin code the way you want.
Since, as mentioned earlier in this tutorial, every plugin is also a composer package, we want it to look like most other composer packages do.
Their directory naming is mostly lowercase and most of them store their main code into a `src` directory, just like our [Shopware platform code](https://github.com/shopware/platform) itself.
While you're free to structure your plugin in whichever way you want, we recommend you to do it this way.

```json
{
...
    "autoload": {
        "psr-4": {
            "Swag\\BundleExample\\": "src/"
        }
    },
}
```

Also required is the related namespace you want to use in your plugin.
Usually you'd want it to look something like this: `YourVendorPrefix\YourPluginName`

### extra

Last but not least is the [extra](https://getcomposer.org/doc/04-schema.md#extra) property, which can fit ANY value.
Shopware 6 is using it for fetching a few more meta information, such as a `copyright`, a `label` and a `plugin-icon` path. 
Another important value is the fully qualified class name (later referred to as 'FQCN') of your plugin's base class, so Shopware 6 knows where to look for your plugin's base class.
This is necessary, since due to your freedom to setup your plugin structure yourself, Shopware 6 also has no clue where your plugin's base class could be.

```json
{
    ...
    "extra": {
        "shopware-plugin-class": "Swag\\BundleExample\\BundleExample",
        "copyright": "(c) by shopware AG",
        "label": {
            "de-DE": "Beispiel für Shopware",
            "en-GB": "Example for Shopware"
        }
    }
}
```

### Final composer.json

Here's what the final `composer.json` looks like once all values described were set.

```json
{
    "name": "swag/bundle-example",
    "description": "Bundle example",
    "version": "v1.0.0",
    "license": "MIT",
    "authors": [
        {
            "name": "shopware AG",
            "role": "Manufacturer"
        }
    ],
    "type": "shopware-platform-plugin",
    "autoload": {
        "psr-4": {
            "Swag\\BundleExample\\": "src/"
        }
    },
    "extra": {
        "shopware-plugin-class": "Swag\\BundleExample\\BundleExample",
        "copyright": "(c) by shopware AG",
        "label": {
            "de-DE": "Beispiel für Shopware",
            "en-GB": "Example for Shopware"
        }
    }
}
```

## Plugin base class

In order to get a fully functional plugin running, we still need the plugin's base class.
As you probably noticed from the `composer.json`, our main source is going to be in a `src` directory with the namespace `Swag\BundleExample`.
So that's also where the plugin's base class will be at, so create a new file named after your plugin in the `<plugin root>/src` directory.
In this example, it will be named `BundleExample`:

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample;

use Shopware\Core\Framework\Plugin;

class BundleExample extends Plugin
{
}
```

Your plugin base class **always** has to extend from `Shopware\Core\Framework\Plugin` in order to work properly.
The namespace and class name are set as defined in the composer.json. That's it for now, the plugin would already be recognized
by Shopware 6 and is installable.

## Installing the plugin

Now it's time to check if everything was done correctly until this point. First you have to refresh the plugins.

```bash
./bin/console plugin:refresh
```

Try to install your new plugin in the Pluginmanager in the Administration. You can find the Pluginmanager under "Settings" > "System" > "Plugins".

If you're more into using the CLI, you can also execute the following command from inside your
development template root.
```bash
./bin/console plugin:install --activate --clearCache BundleExample
```

If everything was done right, it should install without any issues.

Head over to the [next step](./030-database.md) to create new database tables for your plugin using migrations.
