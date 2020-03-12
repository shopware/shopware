[titleEn]: <>(Extending a storefront block)
[metaDescriptionEn]: <>(This HowTo will give a short example to extend a storefront block.)
[hash]: <>(article:how_to_extend_storefront_blocks)

## Overview

In this HowTo you will see a very short example on how you can extend a storefront block.
For simplicities' sake, only the logo is replaced with an "Hello world!" text.

## Setup

This HowTo requires you to already have a basic plugin running.
If you don't know how to do this in the first place, have a look at our [plugin quick start guide](./../2-internals/4-plugins/010-plugin-quick-start.md).
That's already all the setup you need.

## Extending the storefront

### Setting up your view directory

First of all you need to register your plugin's own view path, which basically represents a path in which Shopware 6 is looking for template-files.
By default, Shopware 6 is looking for a directory called `views` in your plugin's `Resources` directory, so 
the path could look like this: `<plugin root>/src/Resources/views`

If you're unhappy with this default path, you can override it in your plugin's base class using the [getViewPaths](./../2-internals/4-plugins/020-plugin-base-class.md) method.

### Finding the proper template

As mentioned earlier, this HowTo is only trying to replace the 'demo' logo with a 'Hello world!' text.
In order to find the proper template, you can simply search for the term 'logo' inside of the `<shopware root>/src/Storefront` directory.
This will ultimately lead you to [this](https://github.com/shopware/platform/blob/master/src/Storefront/Resources/views/storefront/layout/header/logo.html.twig) file.

Overriding this file now requires you to copy the exact same directory structure starting from the `views` directory.
In this case, the file `logo.html.twig` is located in a directory called `views/layout/header`, so make sure to remember this path.

### Overriding the template

Now, that you've found the proper template for the logo, you can override it.

This is done by creating the very same directory structure for your custom file, which is also being used in the Storefront core.
As you hopefully remember, you have to set up the following directory path in your plugin: `<plugin root>/src/Resources/views/storefront/layout/header` 
In there you want to create a new file called `logo.html.twig`, just like the original file.
Once more to understand what's going on here:
In the Storefront code, the path to the logo file looks like this: `Storefront/Resources/views/storefront/layout/header/logo.html.twig`
Now have a look at the path being used in your plugin: `<plugin root>/src/Resources/views/storefront/layout/header/logo.html.twig`

Starting from the `views` directory, the path is **exactly the same**, and that's the important part for your custom template to be
loaded automatically.

### Custom template content

It's time to fill your custom `logo.html.twig` file.
First of all you want to extend from the original file, so you can override its blocks.

Put this line at the very beginning of your file
```twig
{% sw_extends '@Storefront/storefront/layout/header/logo.html.twig' %}
```

This is simply extending the `logo.html.twig` file from the Storefront bundle.

You want to replace the logo with some custom text, so let's have a look at the original file.
In there you'll find a block called `layout_header_logo_link`. Its contents then would create an anchor tag, which is not necessary
for our case anymore, so this seems to be a great block to override.

To override it now, just add the very same block into your custom file and replace its contents:
```twig
{% sw_extends '@Storefront/storefront/layout/header/logo.html.twig' %}

{% block layout_header_logo_link %}
    <h2>Hello world!</h2>
{% endblock %}
```

If you wanted to append your text to the logo instead of replacing it, you could add a line like this to your override: `{{ parent() }}`

And that's it already, you're done already.
Install your plugin, clear the cache and refresh your storefront to see your changes in action.

## Source

There's a GitHub repository available, containing a full example source.
Check it out [here](https://github.com/shopware/swag-docs-extending-block).
