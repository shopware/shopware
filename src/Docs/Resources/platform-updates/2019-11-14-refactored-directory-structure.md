[titleEn]: <>(Refactored resources directories)


We have adjusted the entry points for administration and storefront resources so that there are no naming conflicts anymore. It is no longer possible to adjust the paths to the corresponding sources. 

The new structure looks as follows:
```
MyPlugin
 └──Resources
    ├── theme.json
    ├── app
    │   ├── administration
    │   │   └── src
    │   │       ├── main.js
    │   │       └── scss
    │   │           └── base.scss
    │   └── storefront
    │       ├── dist
    │       └── src
    │           ├── main.js
    │           └── scss
    │               └── base.scss
    ├── config
    │   ├── routes.xml
    │   └── services.xml
    ├── public
    │   ├── administration
    │   └── storefront
    └── views
        ├── administration
        ├── documents
        └── storefront
```

* We unified the twig template directory structure of the core, administration and storefront bundle. Storefront template are now stored in a sub directory named `storefront`. This has an effect on the previous includes and extends:

    Before: 
    `{% sw_extends '@Storefront/base.html.twig' %}`

    After:
    `{% sw_extends '@Storefront/storefront/base.html.twig' %}`

* We removed the corresponding public functions in the `Bundle.php`:
    * `getClassName`
    * `getViewPaths`
    * `getAdministrationEntryPath`
    * `getStorefrontEntryPath`
    * `getConfigPath`
    * `getStorefrontScriptPath`
    * `getStorefrontStylePath`
    * `getAdministrationStyles`
    * `getAdministrationScripts`
    * `getRoutesPath`
    * `getServicesFilePath`

* We changed the accessibility of different internal `Bundle.php` functions
    * `registerContainerFile` from `protected` to `private`
    * `registerEvents` from `protected` to `private`
    * `registerFilesystem` from `protected` to `private`
    * `getContainerPrefix` from `protected` to `final public`


