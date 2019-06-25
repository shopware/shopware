[titleEn]: <>(Using custom CSS and Javascript in the Storefront)
[metaDescriptionEn]: <>(Quite often your plugin will have to change a few templates for the Storefront. Those might require custom stylings to look neat and a few lines of javascript, to add special functionality. This How To will explain how this is done.)

Quite often your plugin will have to change a few templates for the Storefront.
Those might require custom stylings to look neat and a few lines of javascript, to add special functionality.
This How To will explain how this is done.

## Setup

You won't learn to create a plugin in this guide, head over to our [Plugin quick start guide](./../2-internals/4-plugins/010-plugin-quick-start.md) to
create your plugin first.
The plugin in this example will be called `StorefrontAssets`.

## Injecting into the storefront

In Shopware 6 all of the frontend assets have to be located in the `public` directory of the Shopware 6 development template
in order to be accessible by the browser. Your plugin shouldn't put its assets into another directory outside of its scope though - 
but how do those assets from plugins actually work then?

All of those assets, such as CSS and javascript, are processed by [webpack](https://webpack.js.org/) to automatically
handle that issue, as well as several other helpful things, e.g. compiling [SCSS files](http://compass-style.org/).

For that reason, you have to define an entry-point, so webpack knows where to get started.
In Shopware 6, this entry-point is a `main.js` file inside of the following directory:
`<plugin root>/src/Resources/storefront/`

*Note: For administration files, the path would be same, but ending with `administration` of course.*

Go ahead and create a `main.js` in that directory.

### Adding styles

Yes, this is a javascript file and you might wonder, how you're supposed to add custom styles now.
This is **not** done by adding a `main.css` file as well. How comes?

In Shopware 6, you can use `.scss` files as well, and you might know, that those needs to be compiled first.
As already mentioned, that's just another thing webpack can do for you - but that also means, that your styles have to be part of 
the webpack entry point somehow.

The solution to all this, is importing the styles into your javascript `main.js` file.

```js
import './styles/main.scss';
```

This is now requesting a `main.scss` file inside of a `styles` directory, so create that one.
The directory has to be created inside your `storefront` directory, next to the `main.js` file.
Of course, you can choose whatever location and directory you want here.

Inside of the `.scss` file, add some styles to see if it's working. In this example, the background of the `body` will be changed.

```scss
body {
    background: blue;
}
```

### Loading the assets

You've added the `main.js` file, which will be automatically loaded by Shopware 6 as an entry point for webpack.
In there, you've imported your custom styles and webpack will now take care of that and generate a separate `.js` and compiled `.css` file
inside of the development template's `public` directory.

You've never loaded those files from the `public` directory though.

For that purpose, you'll have to extend the templates.
If you don't know how to extend a Storefront template via plugin, head over to our explanation on [How to extend a Storefront block](https://docs.shopware.com/en/shopware-platform-dev-en/how-to/extending-storefront-block).

The proper files for that are the files `base.html.twig`, which includes the javascript files, and `layout/meta.html.twig`, which includes all style files.

First of all, there's the block `layout_head_stylesheet`, responsible for the styles, inside of the file `layout/meta.html.twig`.
Simply extend from the template mentioned above in your plugin, and overwrite this block to add your custom styles.

```twig
{% sw_extends '@Storefront/layout/meta.html.twig' %}

{% block layout_head_stylesheet %}
    {{ parent() }}

    <link rel="stylesheet" href="{{ asset('bundles/storefrontassets/storefront/css/storefront-assets.css') }}" />
{% endblock %}
```

This file's path would now look like this: `<plugin root/Resources/views/layout/meta.html.twig`.

Technically, that would be it already - your styles are now loaded. You wouldn't load any custom javascript code as of now though.

Imagine you didn't only import your custom styles via your `main.js` file, but also added some actual javascript code to it.
You also have to load the generated `.js` file from the `public` directory.
For that case, you should use the block `base_script_hmr_mode` from the file `base.html.twig`.

```twig
{% sw_extends '@Storefront/base.html.twig' %}

{% block layout_head_stylesheet %}
    {{ parent() }}

    <link rel="stylesheet" href="{{ asset('bundles/storefrontassets/storefront/css/storefront-assets.css') }}" />
{% endblock %}

{% block base_script_hmr_mode %}
    {{ parent() }}

    {% if isHMRMode %}
        <script type="text/javascript" src="{{ app.request.server.get('APP_URL') }}:9999/js/storefront-assets.js"></script>
    {% else %}
        <script type="text/javascript" src="{{ asset('bundles/storefrontassets/storefront/js/storefront-assets.js') }}"></script>
    {% endif %}
{% endblock %}
```

The condition `isHMRMode` is actually true, if you're using the Hot Module Replacement server by using the following command from inside the development template directory:
```bash
./psh.phar storefront:hot
```

In that case, the URL to load your javascript from differs a little.
Otherwise, fetch the `.js` file the same way like you've fetched your custom `.css` files.

### Testing its functionality

Now you want to test if your custom styles actually apply to the Storefront.
For this, you have to execute the compiling and building of the `.js` and `.css` files first.
This is done using the following command from inside the development template directory:

```bash
./psh.phar storefront:build
```

**Important note: This will also generate a `public` directory inside your plugin. Always ship this directory with your plugin,
do not exclude or remove it!**

And that's it! Open the Storefront and see it turning blue, due to your custom styles!

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-storefront-assets).