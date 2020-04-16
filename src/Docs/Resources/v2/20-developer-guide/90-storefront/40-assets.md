[titleEn]: <>(Assets)
[hash]: <>(article:developer_storefront_assets)

Its a common use case to change some storefront templates in your plugin - Enabling customisation.
Your plugin may require some custom styling to look decent and a few lines of JavaScript to add special features.

## Injecting into the storefront

When it comes to CSS and SCSS, they are processed by a PHP SASS compiler.

JavaScript cannot be compiled by PHP, so [webpack](https://webpack.js.org/) is being used for that.
This also implies that you must ship your plugin with the JavaScript already compiled. If you do not wish 
to create a custom webpack configuration, you are able to use the Shopware Webpack build 
Configuration. You need to define an entry point so that Webpack knows where to start.

## Using custom SCSS

In order to add some custom SCSS in your plugin, you just need to add a `base.scss` with your custom styles in the
following place:
```
.
├── composer.json
└── src
    ├── Resources
    │   ├── app
    │   │   └── storefront
    │   │       └── src
    │   │           └── scss
    │   │               └── base.scss <-- SCSS entry
    │   ├── config
    │   │   └── services.xml
    │   └── views
    │       └── storefront
    │           └── base.html.twig
    └── TestAsset.php
```

To apply your styles and test them, please use some test code:
```
// YourPluginRoot/src/Resources/app/storefront/src/scss/base.scss
body {
    background-color: blue;
}
```
Afterwards, you need to compile your template by using the `bin/console theme:compile` command - Your custom styles 
should be available then.

## Using custom JS

Since Shopware knows where your style files are located, they are automatically compiled, compressed 
and loaded into the storefront. In the case of JavaScript, 
you have your 'main.js' as entry point within the following directory:
```
.
├── composer.json
└── src
    ├── Resources
    │   ├── app
    │   │   └── storefront
    │   │       └── src
    │   │           └── js
    │   │               └── main.js <-- JS entry
    │   ├── config
    │   │   └── services.xml
    │   └── views
    │       └── storefront
    │           └── base.html.twig
    └── TestAsset.php
```

Add some test code in order to see if it works out:
```
// YourPluginRoot/src/Resources/app/storefront/src/src/main.js
console.log('MyPlugin JS loaded');
```

In the end, by running the command `bin/console theme:compile` your custom JS plugin is loaded. 
By default, the compiled js file is saved as 
<plugin root>/src/resources/app/storefront/dist/storefront/js/<plugin-name>.js`.
It is detected by Shopware automatically.

## Using custom assets

If you want to use custom assets, please put thouse assets here:
​
```
# PluginRoot
.
├── composer.json
└── src
    ├── Resources
    │   ├── public
    │   │   └── your-image.png <-- Asset file here
    └── YourPlugin.php
```
​
Next, please ruun `bin/console asset:install` command. This will copy your plugin assets over to the platform 
`public/bundles` folder:
​
```
# shopware-root/public
.
├── administration
├── framework
├── storefront
└── yourplugin
    └── le-logo.png <-- Your asset is copied here
```
​
### Linking to assets:
​
You can link to the asset with the twig 
[asset](https://symfony.com/doc/current/templates.html#linking-to-css-javascript-and-image-assets) function:
​
```
<img src="{{ asset('bundles/yourplugin/your-logo.png') }}">
```

## HowTo guide concerning storefront assets

If you want a detailed tutorial on how to use custom storefront assets, we got you covered. Please refer to 
[the guide] for further information. 
