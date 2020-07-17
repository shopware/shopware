[titleEn]: <>(Directory Structure)
[hash]: <>(article:references_directory_structure)
 
After setting up Shopware 6 you should import the project into your favorite IDE and take a deeper look into the directory structure of the application.

This guide will give you a brief initial overview onto the structure. We take this step before diving deeper into the architecture, general concepts and detailed specification to give you some sense of familiarity.

## The project

If you followed the installation guide, you should have already noticed that Shopware 6 consists of a minimum of two repositories for you to check out. You work inside of a *development template* that is an adjusted Symfony application template and the actual sources of Shopware 6.

## The development template

If you are familiar with Symfony applications you will recognize most of the directories upfront. The application template is a slight deviation of the Symfony project template. When opening the root you will be greeted by this file listing:

```
<project root>
└── bin
└── build
└── config
└── custom
└── dev-ops
└── platform
└── public
└── src
└── tests
└── var
└── vendor
└── .editorconfig
└── .env
└── .env.dist
└── .gitignore
└── .psh.yaml.dist
└── README.md
└── composer.json
└── composer.lock
└── docker-compose.override.yml
└── docker-compose.yml
└── license.txt
└── phpunit.xml.dist
└── psh.phar

```

Since this is mostly a Symfony boilerplate, we will ignore most files and folders for now. The noteworthy exceptions are:

`custom`
 : An empty directory in a fresh install. The Shopware Platform loads plugins from here. If you want to get started with plugin development this directory is for you.
 
`src`
 : Contains small bootstrapping helpers. If you want to customize the application outside of the plugin scope feel free to start developing here.
 
`bin`
 : Call maintenance scripts through `bin/console`.
 
`public`
 : As the name suggests this directory should be public to the web. The `index.php` is the entry point for all requests. Assets like stylesheets, images and JavaScript files get linked into this directory.
 
`dev-ops`
 : Contains utilities for deployment, development and continuous integration.

## Shopware 6 root

Shopware 6 can be found in `platform/`. This directory also contains a few configuration files specific to the platform. The real application resides in the `src/` subdirectory and conforms to the Symfony Bundle standard.
 
```
<platform/src>
└── Administration
└── Core
└── Docs
└── Storefront
```

Here you find the three main components of the Application. The `Core` is the heart of Shopware 6 and contains the eCommerce related data structures and workflows and all REST-API's.

`Storefront` and `Administration` are specialized consumers of the `Core`. `Administration` is a small Symfony bundle responsible for building and delivering the administrative user interface of Shopware 6. The `Storefront` provides the frontend of the shop.

Oh and by the way: This document amongst the whole documentation you are currently reading can be found in `Docs` ;).

## Core

The Core is the basis of Shopware 6. On the inside you find the majority of the PHP source code.

```
<platform/src/Core>
└── Checkout
└── Content
└── Flag
└── Framework
└── Migration
└── Profiling
└── composer.json
└── Defaults.php
└── Kernel.php
└── locales.php
└── phpunit.xml.dist
└── PlatformRequest
└── README.md
└── schema.sql
└── StorefrontRequest.php
└── TestBootstrap.php
```

The Core itself is structured by the major sectors of the eCommerce domain. All checkout related functionality can be found in the `Checkout` directory, all Content related functionality in `Content` and commonly necessary functionality is in `System`. Inside each directory you find a number of functional modules managing one specific context in Shopware 6.

A rather special role plays the `Framework` directory which contains the technical basis for Shopware 6. Therefore it provides abstraction for the Platform.

## Administration

The Administration component looks almost like a stock Symfony bundle, and is just a thin PHP wrapper around the single page application management ui. This application is fairly deep inside of the bundles structure and can be found in `Administration/Resources/app/administration`.

```
<platform/src/Administration/Resources/app/administration>
└── build
└── config
└── node_modules
└── src
└── static
└── test
└── .babelrc
└── .eslintignore
└── .eslintrc.js
└── .gitignore
└── .postcssrc.js
└── README.md
└── index.html.tpl
└── jsdoc.config.js
└── package-lock.json
└── package.json
└── yarn.lock
```

This one follows the common structure for npm packages. Again most files are configuration, most directories convention. The application itself can be found in the `src` directory. When you open that one you should see this listing: 

```
<platform/src/Administration/Resources/app/administration/src/>
└── app
└── core
└── flag
└── module
```

The src directory of the administration contains four directories. In `core` you find the technical basis for the admin. It contains the bootstrapping, data handling and a shared service layer. In `app` you find the binding to the concrete node modules deployed with the application. And last but far from least in `module` you find the actual application components, views and the styling information. 


## Storefront

The `Storefront` directory follows the Symfony Bundle standard with the addition of the actual storefront.      

```
<platform/src/Storefront/>
└── DependencyInjection
└── Event
└── Framework
└── Page
└── PageController
└── Pagelet
└── PageletController
└── Resources
└── Test
└── .gitignore
└── README.md
└── Storefront.php
└── StorefrontRequest.php
└── composer.json
└── phpunit.xml.dist
```

Again the structure reflects a stock Symfony bundle. The most noteworthy directories are:

`Framework`
 : The storefront comes with its own set of technical necessities, these can be found here
 
`Page` & `Pagelet`
 : The storefront renders full html pages. These pages and their components can be found here. 

`PageController` & `PageletController`
 : Controllers that handle the Requests and render templates.

`Resources`
 :  As the Storefront actually delivers html content, there is a vast mass twig templates, jQuery plugins and Sass stylesheets present.
 
## Conclusion

With this knowledge you should be able to direct your attention to the various parts of Shopware 6 and find the places that you are searching for. 
