Storefront Component
====================

The Storefront component is a frontend for Shopware\Core written in PHP. 

This repository is considered **read-only**. Please send pull requests
to our [main Shopware\Core repository](https://github.com/shopware/platform).


Getting started
---------

To compile the assets (scss/javascript) you have to run the webpack compiler.
This is easily done by executing the following commands in the shopware root folder via the `psh.phar`.

- storefront:dev        Builds the project for development
- storefront:hot        Starts the hot module reloading server
- storefront:hot-proxy  Same as hot, but starts a proxy server with live reload without the need to change twig paths
- storefront:install    Installs the node.js dependencies
- storefront:build      Builds the project for production

For example:
```
$ ./psh.phar storefront:dev
```

It's recommended to use the `storefront:hot-proxy` command when developing, so the files will be compiled as soon as they change.

It's also possible to use the webpack-dev-server with hot module reloading (HMR) activated.
To be able to use HMR you have to run the command `storefront:hot` and set the `isHMRMode` variable,
located in `platform/src/Storefront/Resources/views/storefront/base.html.twig`, to true.


Resources
---------

  * [Documentation](https://developers.shopware.com)
  * [Contributing](https://developers.shopware.com/community/contributing-code/)
  * [Report issues](https://github.com/shopware/platform/issues) and
    [send Pull Requests](https://github.com/shopware/platform/pulls)
    in the [main Shopware\Core repository](https://github.com/shopware/platform)
