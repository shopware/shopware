[titleEn]: <>(Creating a new theme)
[hash]: <>(article:theme_create)

## Difference between "themes" and "regular" plugins

There are basically two ways to change the appearance of the storefront. You can have "regular" plugins
which main purpose is to add new functions and change the behavior of the shop. 
These Plugins might also contain scss/css and javascript to be able to embed their new features into
the storefront.

A shop owner can install your plugin over the plugin manager and your scripts and styles will 
automatically be embedded. The theme which is currently selected by the shop owner will be
recompiled with your custom styles.

The second way to change the appearance of the storefront is to create a theme. The main purpose of themes
is to change the appearance of the storefront and they behave a bit different compared to "regular" plugins.

Technically a theme is also a plugin but it will not only appear in the plugin manager of the administration,
it will also be visible in the theme manger once activated in the plugin manager.
To distinguish a theme plugin from a "regular" plugin you need to implement the Interface `Shopware\Storefront\Framework\ThemeInterface`
A theme can inherit from other themes, overwrite the default configuration (colors, fonts, media) and
add new configuration options.

## Creating a new theme

Open your terminal and run any of these commands to create a new theme.

```bash
# run this inside the project directory to create a new theme
$ bin/console theme:create MyTheme
	
# you should get an output like this:
	
Creating theme structure under .../development/custom/plugins/MyTheme
```

## Installing your theme

```bash
# run this to let shopware know about your plugin
$ bin/console plugin:refresh

# you should get an output like this

[OK] Plugin list refreshed

Shopware Plugin Service
=======================

--------------------------- ------------------------- ------------- ----------------- -------------------- ----------- -------- ------------- 
Plugin                      Label                     Version       Upgrade version   Author               Installed   Active   Upgradeable  
--------------------------- ------------------------- ------------- ----------------- -------------------- ----------- -------- ------------- 
MyTheme                     Theme MyTheme plugin      6.2                                          No          No       No           
--------------------------- ------------------------- ------------- ----------------- -------------------- ----------- -------- -------------
```

Now you can install your theme and activate it with die following command.

```bash
# run this to install and activate your plugin
$ bin/console plugin:install --activate MyTheme

# you should get an output like this

Shopware Plugin Lifecycle Service
=================================

Install 1 plugin(s):
* Theme MyTheme plugin (6.2)

Plugin "MyTheme" has been installed and activated successfully.


[OK] Installed 1 plugin(s).
```

## Changing current theme

```bash
# run this to change the current storefront theme
$ bin/console theme:change
	
# you will get an interactive prompt to change the 
# current theme of the storefront like this
	
Please select a sales channel:
[0] Storefront | 64bbbe810d824c339a6c191779b2c205
[1] Headless | 98432def39fc4624b33213a56b8c944f
> 0

Please select a theme:
[0] Storefront
[1] MyTheme
> 1

Set "MyTheme" as new theme for sales channel "Storefront"
Compiling theme 13e0a4a46af547479b1347617926995b for sales channel MyTheme	
```

Now your theme is fully installed and you can start your customization.

## Directory structure of a theme

In the file tree below you can see the basic file structure of the generated theme:

```
# move into your theme folder
$ cd custom/plugins/MyTheme
	
# structure of theme
├── composer.json
└── src
    ├── MyTheme.php
    └── Resources
        ├── app
        │   └── storefront
        │       ├── dist
        │       │   └── storefront
        │       │       └── js
        │       │           └── my-theme.js
        │       └── src
        │           ├── assets
        │           ├── main.js
        │           └── scss
        │               ├── base.scss
        │               └── overrides.scss
        └── theme.json
```

## Commands

The theme system can be controlled via CLI with the following commands.

### Theme refresh

Normally new themes are detected automatically but if you want to trigger this process
run the command

```bash
# run 
$ bin/console theme:refresh
```

### Change a theme
After scanning for themes these can be activated using 
```bash
# run this to interactively change a theme
$ bin/console theme:change

#run this to change a theme for all sales channels
$ bin/console theme:change MyCustomTheme --all
```

Notice: `theme:change` will also compile the theme for all new assignments. 

### Compile a theme

Calling the `theme:compile` command will recompile all themes which are assigned to a sales channel.

```bash
# run this to compile the scss and js files
$ bin/console theme:compile
```

## Browser Compatibility Note

Administration: All browsers in the latest version (Edge, (no IE), Chrome, Firefox, Safari, Opera)

Storefront: All browsers in the latest version and additionally IE 11
